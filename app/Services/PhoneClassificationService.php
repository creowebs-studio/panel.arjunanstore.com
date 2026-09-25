<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderValidation;
use App\Models\PhoneHistory;
use App\Models\RuleVersion;

/**
 * Mesin klasifikasi nomor telepon — mereplikasi `Input!AK/AP/AQ` (audit STAGE1 §4.3) PERSIS.
 *
 * Inti aturan (berlawanan dengan intuisi):
 *   Order menjadi POSITIF hanya bila nomor itu BELUM PERNAH punya riwayat yang berarti
 *   (tidak pernah diterima, tidak pernah retur, tidak sedang diproses). Riwayat UNDEL saja
 *   tetap boleh Positif. Nomor yang pernah DITERIMA justru menjadi NEGATIF (anti kirim ulang).
 *
 * Sumber riwayat = view `v_phone_history` (agregat shipments per nomor ternormalisasi).
 */
class PhoneClassificationService
{
    /**
     * Evaluasi murni (tanpa menulis DB) — mudah diuji.
     *
     * @param bool $addressFilled padanan `Input!F` terisi; bila kosong hasil = "" (belum dapat dinilai).
     */
    public function evaluate(?string $normalizedPhone, bool $addressFilled = true): ClassificationResult
    {
        // F =="" → tidak ada keluaran (data belum lengkap).
        if (! $addressFilled || $normalizedPhone === null || $normalizedPhone === '') {
            return new ClassificationResult(
                classification: 'perlu_ditinjau',
                final: '',
                byWa: null,
                lastOrderVerdict: null,
                inProgressResi: null,
                returCount: 0,
                terimaCount: 0,
                reason: 'Data belum lengkap (alamat/nomor) — perlu ditinjau sebelum dinilai.',
                evidence: ['normalized_phone' => $normalizedPhone, 'address_filled' => $addressFilled],
            );
        }

        $h = PhoneHistory::forPhone($normalizedPhone); // null bila nomor belum pernah dikirim

        $inProgressResi = $h && $h->in_progress_count > 0 ? $h->last_resi : null;
        $returCount     = (int) ($h->retur_count  ?? 0);
        $terimaCount    = (int) ($h->terima_count ?? 0);

        // AK (Last Order): status TERAKHIR = retur → Negatif; tanpa riwayat → Positif.
        $lastOrderVerdict = ($h && $h->last_status_internal === 'retur') ? 'Negatif' : 'Positif';

        // AP (OUTPUT By WA).
        if ($inProgressResi !== null) {
            $byWa = 'Negatif';               // ada order masih diproses
        } elseif ($returCount > 0) {
            $byWa = 'Negatif';               // pernah retur
        } elseif ($terimaCount > 0) {
            $byWa = 'Positif';               // pernah diterima, tanpa retur/proses
        } else {
            $byWa = 'Data Belum Tersedia';
        }

        // AQ (OUTPUT AKHIR — penentu ekspor).
        if ($lastOrderVerdict === 'Negatif') {
            $final = 'Output Data Negatif';
        } elseif ($byWa === 'Negatif') {
            $final = 'Output Data Negatif';
        } elseif ($byWa === 'Positif') {
            $final = 'Output Data Negatif';
        } else {
            $final = 'Output Data Positif';
        }

        $classification = $final === 'Output Data Positif' ? 'positif' : 'negatif';

        $reason = match (true) {
            $h === null            => 'Nomor belum punya riwayat pengiriman sama sekali → Positif.',
            $inProgressResi !== null => 'Ada order masih diproses (resi ' . $inProgressResi . ') → Negatif.',
            $returCount > 0        => "Pernah retur ({$returCount}×)" . ($lastOrderVerdict === 'Negatif' ? ', order terakhir RETUR' : '') . ' → Negatif.',
            $terimaCount > 0       => "Sudah pernah diterima ({$terimaCount}×) → tidak dikirim ulang → Negatif.",
            default                => 'Riwayat hanya berisi status sementara/undel (belum diterima/retur) → Positif.',
        };

        return new ClassificationResult(
            classification: $classification,
            final: $final,
            byWa: $byWa,
            lastOrderVerdict: $lastOrderVerdict,
            inProgressResi: $inProgressResi,
            returCount: $returCount,
            terimaCount: $terimaCount,
            reason: $reason,
            evidence: [
                'normalized_phone'    => $normalizedPhone,
                'has_history'         => $h !== null,
                'last_status_internal' => $h->last_status_internal ?? null,
                'total_shipments'     => (int) ($h->total_shipments ?? 0),
            ],
        );
    }

    /**
     * Jalankan evaluasi lalu simpan jejaknya pada order (OrderValidation + kolom order).
     */
    public function record(Order $order, bool $addressFilled = true): ClassificationResult
    {
        $result = $this->evaluate($order->customer_phone_normalized, $addressFilled);
        $rule   = RuleVersion::active(); // versi 'phone_classification' aktif

        $order->classification = $result->classification;
        $order->rule_version_id = $rule?->id;
        $order->validated_at    = now();
        $order->save();

        OrderValidation::create([
            'order_id'          => $order->id,
            'rule_version_id'   => $rule?->id,
            'result'            => $result->final,
            'by_wa'             => $result->byWa,
            'last_order_status' => $result->lastOrderVerdict,
            'in_progress_resi'  => $result->inProgressResi,
            'retur_count'       => $result->returCount,
            'terima_count'      => $result->terimaCount,
            'reason'              => $result->reason,
            'evidence'           => $result->evidence,
            'checked_at'         => now(),
        ]);

        return $result;
    }
}
