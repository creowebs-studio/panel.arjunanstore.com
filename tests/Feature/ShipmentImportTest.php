<?php

namespace Tests\Feature;

use App\Models\DataIssue;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Import\MengantarRowMapper;
use App\Services\Import\ShipmentImporter;
use App\Services\PhoneNormalizer;
use Database\Seeders\CarrierStatusMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji Alur C+D (prompt.md §6/§7): impor → master resi + riwayat + Data Error,
 * dengan jaminan idempotensi & aturan "data lama tak membalik status baru".
 */
class ShipmentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MengantarRowMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierStatusMappingSeeder::class);
        $this->user = User::create(['name' => 'Pengiriman', 'email' => 'pengiriman@arj.test', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->mapper = new MengantarRowMapper(new PhoneNormalizer());
    }

    /** @param array<int,array<string,string>> $rawRows */
    private function makeBatch(array $rawRows): ImportBatch
    {
        $batch = ImportBatch::create([
            'batch_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'platform'   => 'mengantar',
            'source_filename' => 'hasil.csv',
            'uploaded_by' => $this->user->id,
            'status'     => 'preview',
            'total_rows' => count($rawRows),
        ]);
        foreach (array_values($rawRows) as $i => $raw) {
            ImportRow::create([
                'import_batch_id' => $batch->id,
                'row_number'      => $i + 1,
                'tracking_id'     => $raw['Tracking ID'] ?? null,
                'platform_order_id' => $raw['Order ID'] ?? null,
                'raw_data'        => $raw,
                'mapped_data'     => $this->mapper->map($raw, 'mengantar'),
                'result'          => 'new',
            ]);
        }

        return $batch;
    }

    private function raw(array $over = []): array
    {
        return array_merge([
            'Expedition'  => 'JNE',
            'Order ID'    => 'ORD-1001',
            'Tracking ID' => 'RESI-ABC',
            'Customer Name' => 'Budi',
            'Phone'       => '0812-3456-7890',
            'Status'      => 'ON DELIVERY',
            'Create Date' => '2026-06-01 09:00:00',
            'Last Update' => '2026-06-02 10:00:00',
            'COD'         => '150000',
            'Qty'         => '1',
            'Remark1'     => '040601A1C1PG0001',
        ], $over);
    }

    private function importer(): ShipmentImporter
    {
        return new ShipmentImporter(new MengantarRowMapper(new PhoneNormalizer()));
    }

    public function test_new_row_creates_shipment_and_first_event(): void
    {
        $batch = $this->makeBatch([$this->raw()]);
        $this->importer()->process($batch);

        $s = Shipment::where('tracking_id', 'RESI-ABC')->first();
        $this->assertNotNull($s);
        $this->assertSame('6281234567890', $s->customer_phone); // dinormalisasi string
        $this->assertSame('dikirim', $s->status_internal);       // ON DELIVERY → dikirim
        $this->assertSame(1, $s->statusEvents()->count());
        $this->assertSame(1, (int) $batch->fresh()->new_rows);
    }

    public function test_reimport_same_rows_is_idempotent(): void
    {
        $this->importer()->process($this->makeBatch([$this->raw()]));
        $this->importer()->process($this->makeBatch([$this->raw()]));

        $this->assertSame(1, Shipment::count(), 'resi tidak boleh ganda');
        $this->assertSame(1, Shipment::first()->statusEvents()->count(), 'event identik tidak ditulis ulang');
    }

    public function test_unknown_status_creates_data_issue_and_null_internal(): void
    {
        $batch = $this->makeBatch([$this->raw(['Status' => 'CANCELLED', 'Tracking ID' => 'RESI-UNK'])]);
        $this->importer()->process($batch);

        $s = Shipment::where('tracking_id', 'RESI-UNK')->first();
        $this->assertNotNull($s);
        $this->assertNull($s->status_internal);
        $this->assertDatabaseHas('data_issues', ['type' => 'status_unmapped', 'shipment_id' => $s->id, 'status' => 'open']);
    }

    public function test_remark_with_xx_creates_remark_issue(): void
    {
        $this->importer()->process($this->makeBatch([$this->raw(['Remark1' => '040601XXXXXX0002', 'Tracking ID' => 'RESI-XX'])]));

        $this->assertDatabaseHas('data_issues', ['type' => 'remark_unmapped', 'status' => 'open']);
    }

    public function test_missing_key_row_becomes_error_and_issue(): void
    {
        $batch = $this->makeBatch([$this->raw(['Tracking ID' => '', 'Order ID' => ''])]);
        $this->importer()->process($batch);

        $this->assertSame(1, (int) $batch->fresh()->error_rows);
        $this->assertDatabaseHas('data_issues', ['type' => 'required_missing']);
        $this->assertSame(0, Shipment::where('tracking_id', '')->count());
    }

    public function test_older_import_does_not_flip_newer_status(): void
    {
        // Master saat ini: SUDAH diterima, update terbaru 2026-06-10.
        $this->importer()->process($this->makeBatch([
            $this->raw(['Status' => 'DELIVERED', 'Last Update' => '2026-06-10 12:00:00']),
        ]));
        $this->assertSame('diterima', Shipment::first()->status_internal);

        // Impor baris LAMA (status dikirim, update 2026-06-03) untuk resi yang sama.
        $this->importer()->process($this->makeBatch([
            $this->raw(['Status' => 'ON DELIVERY', 'Last Update' => '2026-06-03 08:00:00']),
        ]));

        $s = Shipment::first();
        $this->assertSame('diterima', $s->status_internal, 'status terkini tidak boleh mundur');
        $this->assertSame(2, $s->statusEvents()->count(), 'riwayat lama tetap dicatat');
    }

    public function test_double_tracking_in_same_file_is_not_duplicated(): void
    {
        // Nomor resi sama dua kali DALAM satu file (audit §7.3 #3).
        $batch = $this->makeBatch([
            $this->raw(),
            $this->raw(['Last Update' => '2026-06-05 08:00:00', 'Status' => 'DELIVERED']),
        ]);
        $this->importer()->process($batch);

        $this->assertSame(1, Shipment::count(), 'resi ganda tidak membuat baris baru');
        $this->assertSame(1, (int) Shipment::first()->duplicate_count);
        $this->assertSame(1, (int) $batch->fresh()->duplicate_rows);
        $this->assertDatabaseHas('data_issues', ['type' => 'double_resi', 'status' => 'open']);
    }

    public function test_reprocess_errors_only_touches_error_rows(): void
    {
        $batch = $this->makeBatch([
            $this->raw(),                                                        // sukses → new
            $this->raw(['Tracking ID' => '', 'Order ID' => '']),                 // error: kunci kosong
        ]);
        $this->importer()->process($batch);

        $this->assertSame(1, (int) $batch->fresh()->new_rows);
        $this->assertSame(1, (int) $batch->fresh()->error_rows);
        $this->assertSame(1, Shipment::count());

        // Admin memperbaiki sumbernya (raw_data diberi kunci) → proses ulang baris error saja.
        $errRow = $batch->rows()->where('result', 'error')->first();
        $errRow->update(['raw_data' => $this->raw(['Tracking ID' => 'RESI-FIX', 'Order ID' => 'ORD-FIX'])]);

        $batch = $this->importer()->reprocessErrors($batch->fresh());

        $this->assertSame('new', $errRow->fresh()->result, 'baris error yang diperbaiki menjadi berhasil');
        $this->assertSame(2, (int) $batch->new_rows, 'counter dihitung ulang: 2 baris berhasil');
        $this->assertSame(0, (int) $batch->error_rows);
        $this->assertSame(2, Shipment::count());
        $this->assertSame(1, Shipment::where('tracking_id', 'RESI-ABC')->first()->statusEvents()->count(),
            'baris yang sudah berhasil tidak diproses ulang');
    }
}
