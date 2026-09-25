<?php

namespace App\Services\Import;

use App\Models\DataIssue;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentStatusEvent;
use Illuminate\Support\Facades\DB;

/**
 * Mesin pemroses impor (Alur C+D — prompt.md §6/§7). Menerapkan baris `import_rows`
 * (hasil pemetaan) ke master resi `shipments` + riwayat `shipment_status_events` + `data_issues`.
 *
 * Jaminan:
 *  - IDEMPOTEN: file yang sama diimpor ulang TIDAK menggandakan shipment/resi/biaya.
 *    Baris identik (status + last_update sama) → result=duplicate (tanpa tulis baru).
 *  - HARIAWAT: setiap perubahan status menambah event; status terkini shipment hanya
 *    bergeser bila data masuk LEBIH BARU (impor data lama tak membalik status baru).
 *  - RETAS: status tak terpetakan / remark "XX" / kunci wajib kosong → Data Error, tetap tersimpan
 *    jejak aslinya agar dapat diperbaiki & diproses ulang.
 */
class ShipmentImporter
{
    /** Kolom shipment yang boleh diisi dari hasil pemetaan. */
    private const SHIPMENT_FIELDS = [
        'platform', 'platform_order_id', 'tracking_id', 'return_resi', 'expedition', 'customer_name',
        'customer_phone', 'address', 'province', 'city', 'district', 'subdistrict', 'zip_code',
        'cod_value', 'product_value', 'goods_desc', 'quantity', 'create_date', 'last_update',
        'status_raw', 'status_internal', 'last_pod_status', 'shipping_fee', 'shipping_discount',
        'cod_fee', 'return_fee', 'remark', 'remark_corrected', 'adv_resi_code', 'cs_resi_code',
        'product_resi_code', 'campaign_name',
    ];

    public function __construct(private MengantarRowMapper $mapper)
    {
    }

    public function process(ImportBatch $batch): ImportBatch
    {
        $batch->update(['status' => 'processing']);
        $seen = [];

        foreach ($batch->rows()->orderBy('row_number')->get() as $row) {
            DB::transaction(function () use ($row, $batch, &$seen) {
                $this->applyRow($row, $batch, $seen);
            });
        }

        return $this->finalize($batch);
    }

    /**
     * Proses ulang HANYA baris berstatus error (prompt.md §6: baris error dapat
     * diperbaiki & diproses ulang tanpa mengulang baris yang sudah berhasil).
     * Baris dipetakan ULANG dari raw_data agar pemetaan status yang baru dibuat
     * langsung terpakai; baris sukses tidak disentuh sama sekali.
     */
    public function reprocessErrors(ImportBatch $batch): ImportBatch
    {
        $batch->update(['status' => 'processing']);
        $seen = [];

        foreach ($batch->rows()->where('result', 'error')->orderBy('row_number')->get() as $row) {
            DB::transaction(function () use ($row, $batch, &$seen) {
                $row->update(['mapped_data' => $this->mapper->map($row->raw_data ?? [], $batch->platform)]);
                $this->applyRow($row->refresh(), $batch, $seen);
            });
        }

        return $this->finalize($batch);
    }

    /** Hitung ulang seluruh counter dari tabel baris — selalu konsisten setelah proses/proses ulang. */
    private function finalize(ImportBatch $batch): ImportBatch
    {
        $c = $batch->rows()
            ->selectRaw("SUM(result = 'new') n, SUM(result = 'update') u, SUM(result = 'duplicate') d, SUM(result = 'error') e")
            ->first();

        $batch->update([
            'status'         => 'done',
            'new_rows'       => (int) ($c->n ?? 0),
            'updated_rows'   => (int) ($c->u ?? 0),
            'duplicate_rows' => (int) ($c->d ?? 0),
            'error_rows'     => (int) ($c->e ?? 0),
            'processed_at'   => now(),
        ]);

        return $batch->refresh();
    }

    /** @return string salah satu: new|update|duplicate|error */
    private function applyRow(ImportRow $row, ImportBatch $batch, array &$seen): string
    {
        $m = $row->mapped_data ?? [];

        if (! empty($m['_missing_key'])) {
            $this->mark($row, 'error', null, 'Tracking ID / Order ID kosong.');
            $this->issue('required_missing', $row, null, null,
                'Tracking ID / Order ID kosong — baris tidak dapat diidentifikasi.', $m);

            return 'error';
        }

        // Double resi: nomor resi sama muncul >1 kali DALAM satu file impor (audit §7.3 #3).
        $tracking = (string) ($m['tracking_id'] ?? '');
        if ($tracking !== '') {
            $key = $batch->platform . '|' . $tracking;
            if (isset($seen[$key])) {
                $dup = Shipment::where('platform', $batch->platform)->where('tracking_id', $tracking)->first();
                $dup?->increment('duplicate_count');
                $this->mark($row, 'duplicate', $dup, 'Double resi dalam file.');
                $this->issue('double_resi', $row, $dup?->id, null,
                    "Tracking ID {$tracking} muncul lebih dari sekali dalam satu file impor.", $m);

                return 'duplicate';
            }
            $seen[$key] = true;
        }

        $shipment = $this->findExisting($m);
        $order = $this->matchOrder($m);

        if (! $shipment) {
            $shipment = Shipment::create($this->only($m) + ['order_id' => $order?->id]);
            $this->addEvent($shipment, $row, $m);
            $this->flagsToIssues($shipment, $row, $m);
            $this->mark($row, 'new', $shipment);

            return 'new';
        }

        // Baris identik (status mentah + waktu update sama) → duplikat, jangan tulis apa pun.
        if ($shipment->status_raw === ($m['status_raw'] ?? null)
            && (string) $shipment->last_update === (string) ($m['last_update'] ?? $shipment->last_update)) {
            $this->mark($row, 'duplicate', $shipment);

            return 'duplicate';
        }

        $incomingNewer = $this->isNewer($shipment, $m);
        $updates = [];
        if ($incomingNewer) {
            // Hanya geser status/biaya "terkini" bila data masuk lebih baru (prompt.md §7).
            $updates = $this->only($m, [
                'status_raw', 'status_internal', 'last_update', 'last_pod_status',
                'shipping_fee', 'shipping_discount', 'cod_fee', 'return_fee', 'remark',
            ]);
        }
        if ($order && ! $shipment->order_id) {
            $updates['order_id'] = $order->id;
        }
        if ($updates) {
            $shipment->update($updates);
        }
        // Riwayat tetap dicatat walau datanya lama (jangan hilangkan event).
        $this->addEvent($shipment, $row, $m, $incomingNewer);
        $this->flagsToIssues($shipment, $row, $m);
        $this->mark($row, 'update', $shipment);

        return 'update';
    }

    private function findExisting(array $m): ?Shipment
    {
        $q = Shipment::where('platform', $m['platform']);
        if (! empty($m['tracking_id'])) {
            return $q->where('tracking_id', $m['tracking_id'])->first();
        }
        if (! empty($m['platform_order_id'])) {
            return $q->where('platform_order_id', $m['platform_order_id'])->first();
        }

        return null;
    }

    /** Kaitkan ke order via reference_code (Remark1 yang kita ekspor) — sumber atribusi. */
    private function matchOrder(array $m): ?Order
    {
        $ref = $m['reference_code'] ?? null;

        return $ref ? Order::where('reference_code', $ref)->first() : null;
    }

    private function addEvent(Shipment $shipment, ImportRow $row, array $m, bool $isCurrent = true): void
    {
        $exists = ShipmentStatusEvent::where('shipment_id', $shipment->id)
            ->where('status_raw', $m['status_raw'] ?? null)
            ->where('status_date', $m['last_update'] ?? $m['create_date'] ?? null)
            ->exists();
        if ($exists) {
            return; // jangan duplikasi event yang sama
        }

        ShipmentStatusEvent::create([
            'shipment_id'       => $shipment->id,
            'status_raw'        => $m['status_raw'] ?? null,
            'status_internal'   => $m['status_internal'] ?? null,
            'pod_status'        => $m['last_pod_status'] ?? null,
            'status_date'       => $m['last_update'] ?? $m['create_date'] ?? now()->toDateTimeString(),
            'shipping_fee'      => $m['shipping_fee'] ?? 0,
            'shipping_discount' => $m['shipping_discount'] ?? 0,
            'cod_fee'           => $m['cod_fee'] ?? 0,
            'return_fee'        => $m['return_fee'] ?? 0,
            'import_row_id'     => $row->id,
        ]);
    }

    /** Data masuk dianggap lebih baru bila last_update >= yang tersimpan. */
    private function isNewer(Shipment $shipment, array $m): bool
    {
        $incoming = $m['last_update'] ?? $m['create_date'] ?? null;
        $current = $shipment->last_update?->toDateTimeString() ?? $shipment->create_date?->toDateTimeString();
        if ($incoming === null) {
            return false;
        }
        if ($current === null) {
            return true;
        }

        return strtotime($incoming) >= strtotime((string) $current);
    }

    private function flagsToIssues(Shipment $shipment, ImportRow $row, array $m): void
    {
        if (! empty($m['_unknown_status'])) {
            $this->issue('status_unmapped', $row, $shipment->id, null,
                'Status "' . ($m['status_raw'] ?? '') . '" belum punya pemetaan internal.', $m);
        }
        if (! empty($m['_remark_unmapped'])) {
            $this->issue('remark_unmapped', $row, $shipment->id, null,
                'Remark/kode kampanye mengandung "XX" (ADV/CS/Produk tak terpetakan): ' . ($m['remark'] ?? ''), $m);
        }
    }

    /** @param array<string,mixed> $m */
    private function only(array $m, ?array $allow = null): array
    {
        $allow ??= self::SHIPMENT_FIELDS;

        return array_intersect_key($m, array_flip($allow));
    }

    private function mark(ImportRow $row, string $result, ?Shipment $shipment = null, ?string $message = null): void
    {
        $row->update([
            'result'       => $result,
            'shipment_id'  => $shipment?->id,
            'error_reason' => $result === 'error' ? ($message ?? $row->error_reason) : $row->error_reason,
        ]);
    }

    private function issue(string $type, ImportRow $row, ?int $shipmentId, ?int $orderId, string $message, array $payload): void
    {
        // Hindari duplikasi issue untuk baris yang sama.
        if (DataIssue::where('import_row_id', $row->id)->where('type', $type)->where('status', 'open')->exists()) {
            return;
        }
        DataIssue::create([
            'type'          => $type,
            'shipment_id'   => $shipmentId,
            'order_id'      => $orderId,
            'import_row_id' => $row->id,
            'message'       => $message,
            'payload'       => array_diff_key($payload, ['_missing_key' => 1, '_unknown_status' => 1, '_remark_unmapped' => 1]),
            'status'        => 'open',
        ]);
    }
}
