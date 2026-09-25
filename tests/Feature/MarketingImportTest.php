<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\DataIssue;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\MarketingDailyReport;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Import\MarketingImporter;
use App\Services\Import\MarketingRowMapper;
use App\Services\Marketing\CampaignCodeResolver;
use App\Services\Marketing\RekapAdvService;
use Database\Seeders\CommissionRuleSeeder;
use Database\Seeders\ReferenceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Alur E (prompt.md §8): impor laporan kampanye marketing — idempotensi, pemetaan kode
 * ADV/CS/Produk, worklist kode tak dikenal (TIDAK disembunyikan), dan rekap ADV↔resi.
 */
class MarketingImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommissionRuleSeeder::class); // aturan spend_ppn (×1.12)
        $this->seed(ReferenceMasterSeeder::class);
        $this->user = User::create([
            'name' => 'Marketing', 'email' => 'marketing@arj.test',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    /** @param array<int,array<string,string>> $rawRows */
    private function makeBatch(array $rawRows): ImportBatch
    {
        $mapper = new MarketingRowMapper();
        $batch = ImportBatch::create([
            'batch_uuid'      => (string) Str::uuid(),
            'platform'        => 'marketing',
            'source_filename' => 'rekap-kampanye.csv',
            'uploaded_by'     => $this->user->id,
            'status'          => 'preview',
            'total_rows'      => count($rawRows),
        ]);

        foreach (array_values($rawRows) as $i => $raw) {
            ImportRow::create([
                'import_batch_id' => $batch->id,
                'row_number'      => $i + 1,
                'raw_data'        => $raw,
                'mapped_data'     => $mapper->map($raw),
                'result'          => 'new',
            ]);
        }

        return $batch;
    }

    /** Baris contoh format tab `Rekap` (audit §8.1/§8.2). */
    private function raw(array $over = []): array
    {
        return array_merge([
            'Awal Pelaporan'             => '2026-06-01',
            'Akhir Pelaporan'            => '2026-06-01',
            'Nama Kampanye'              => 'AM01-GL01-PG',
            'Penayangan'                 => 'Aktif',
            'Atribusi'                   => 'Tautan 7 hari klik',
            'Hasil'                      => '180',
            'Indikator Hasil'            => 'Klik Tautan',
            'Jangkauan'                  => '12.345',
            'Frekuensi'                  => '5,67',
            'Biaya/Hasil'                => 'Rp 2.500',
            'Anggaran Set Iklan'         => 'Rp 450.000',
            'Jenis'                      => 'Anggaran Harian',
            'Jumlah Dibelanjakan (IDR)'  => 'Rp 450.000',
            'Berakhir'                   => '2026-06-01',
            'Impressi'                   => '98.765',
            'CPM'                        => 'Rp 4.556',
            'Klik Tautan'                => '1.234',
            'CPC'                        => 'Rp 365',
            'CTR'                        => '3,37%',
            'Klik Semua'                 => '1.500',
            'CTR Semua'                  => '4,10%',
            'CPC Semua'                  => 'Rp 300',
        ], $over);
    }

    private function importer(): MarketingImporter
    {
        return new MarketingImporter(new CampaignCodeResolver());
    }

    public function test_new_rows_create_campaign_report_and_mapping(): void
    {
        $batch = $this->makeBatch([
            $this->raw(),
            $this->raw(['Nama Kampanye' => 'AM02-AR01-BS', 'Jumlah Dibelanjakan (IDR)' => 'Rp 240.000']),
        ]);
        $batch = $this->importer()->process($batch);

        $this->assertSame(2, (int) $batch->new_rows);
        $this->assertSame(2, Campaign::count());

        $campaign = Campaign::where('name', 'AM01-GL01-PG')->first();
        $this->assertTrue($campaign->is_mapped, 'kode AM01-GL01-PG harus terpetakan');
        $this->assertSame('ADV Contoh Satu', $campaign->advertiser?->name);
        $this->assertSame('Ani', $campaign->csAgent?->name);
        $this->assertSame('P001', $campaign->product?->code);

        $report = MarketingDailyReport::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('2606', $report->periode);
        $this->assertSame(450000.0, (float) $report->spend_raw);
        $this->assertSame(504000.0, (float) $report->spend_ppn, 'spend+PPN = spend × 1.12 (audit §8.2)');
    }

    public function test_reimport_same_file_is_idempotent(): void
    {
        $this->importer()->process($this->makeBatch([$this->raw()]));
        $second = $this->importer()->process($this->makeBatch([$this->raw()]));

        $this->assertSame(1, Campaign::count());
        $this->assertSame(1, MarketingDailyReport::count(), 'laporan tidak boleh ganda');
        $this->assertSame(0, (int) $second->new_rows);
        $this->assertSame(1, (int) $second->duplicate_rows);
    }

    public function test_unmapped_codes_are_not_hidden_and_create_worklist_issue(): void
    {
        $batch = $this->importer()->process($this->makeBatch([
            $this->raw(['Nama Kampanye' => 'AM99-ZZ99-XX']),
        ]));

        $campaign = Campaign::where('name', 'AM99-ZZ99-XX')->first();
        $this->assertNotNull($campaign);
        $this->assertFalse($campaign->is_mapped);
        $this->assertSame(1, (int) $batch->new_rows, 'laporan tetap diimpor walau kode tak dikenal');
        $this->assertSame(1, MarketingDailyReport::count());

        $issue = DataIssue::where('type', 'campaign_unmapped')->first();
        $this->assertNotNull($issue, 'worklist kode tak dikenal harus dibuat');
        $this->assertSame('open', $issue->status);
        $this->assertSame($campaign->id, $issue->campaign_id);
    }

    public function test_changed_values_update_existing_report(): void
    {
        $this->importer()->process($this->makeBatch([$this->raw()]));
        $batch = $this->importer()->process($this->makeBatch([
            $this->raw(['Jumlah Dibelanjakan (IDR)' => 'Rp 500.000']),
        ]));

        $this->assertSame(1, (int) $batch->updated_rows);
        $this->assertSame(1, MarketingDailyReport::count());
        $this->assertSame(560000.0, (float) MarketingDailyReport::first()->spend_ppn);
    }

    public function test_corrupt_derived_metrics_are_recomputed_from_base_columns(): void
    {
        // Sel turunan rusak akibat locale id-ID ('8,0004' → '1128899' dll.) — audit Tahap 6.
        $batch = $this->importer()->process($this->makeBatch([
            $this->raw([
                'Frekuensi' => '1128899',
                'CPM'       => '455628',
                'CTR'       => '1249342',
            ]),
        ]));

        $this->assertSame(1, (int) $batch->new_rows);

        $report = MarketingDailyReport::first();
        $this->assertNotNull($report);
        $this->assertSame(8.0, (float) $report->frequency, 'Frekuensi = Impresi ÷ Jangkauan');
        $this->assertSame(4556.27, (float) $report->cpm, 'CPM = Belanja ÷ Impresi × 1.000');
        $this->assertSame(1.2494, (float) $report->ctr_link, 'CTR = Klik Tautan ÷ Impresi × 100');

        $issue = DataIssue::where('type', 'metric_normalized')->first();
        $this->assertNotNull($issue, 'normalisasi harus tampil di worklist, bukan disembunyikan');
        $this->assertSame(1, (int) ($issue->payload['rows'] ?? 0));
        $this->assertArrayHasKey('Frekuensi', $issue->payload['metrics']);
        $this->assertArrayHasKey('CPM', $issue->payload['metrics']);
    }

    public function test_missing_campaign_name_is_error_with_issue(): void
    {
        $batch = $this->importer()->process($this->makeBatch([
            $this->raw(['Nama Kampanye' => '', 'Awal Pelaporan' => '']),
        ]));

        $this->assertSame(1, (int) $batch->error_rows);
        $this->assertSame(0, Campaign::count());
        $this->assertDatabaseHas('data_issues', ['type' => 'required_missing', 'status' => 'open']);
    }

    public function test_rekap_adv_matches_shipments_and_flags_unmatched(): void
    {
        $this->importer()->process($this->makeBatch([$this->raw()]));

        // Resi yang cocok dimensi kode kampanye (A1/C1/PG — audit §7.1).
        Shipment::create([
            'platform' => 'mengantar', 'tracking_id' => 'MR-RKP-1', 'customer_phone' => '628140000001',
            'status_internal' => 'diterima',
            'cod_value' => 200000, 'shipping_fee' => 22000, 'quantity' => 1,
            'create_date' => '2026-06-01 08:00:00', 'adv_resi_code' => 'A1',
            'cs_resi_code' => 'C1', 'product_resi_code' => 'PG',
        ]);
        // Resi tak cocok kode apa pun → worklist.
        Shipment::create([
            'platform' => 'mengantar', 'tracking_id' => 'MR-RKP-2', 'customer_phone' => '628140000002',
            'status_internal' => 'dikirim',
            'cod_value' => 100000, 'shipping_fee' => 15000, 'quantity' => 1,
            'create_date' => '2026-06-01 09:00:00', 'adv_resi_code' => 'ZZ',
            'cs_resi_code' => 'ZZ', 'product_resi_code' => 'ZZ',
        ]);

        $service = app(RekapAdvService::class);
        $rows = $service->rows(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(1, $row['counts']['diterima']);
        $this->assertSame(1, $row['total']);
        $this->assertSame(504000.0, $row['spend']);
        $this->assertSame(65255.0, $row['laba_kotor'], 'ΣAM rantai OutputResi (audit §9.2)');
        $this->assertSame(10585.0, $row['komisi_cs'], 'ΣAK rantai OutputResi');
        $this->assertSame(65255.0 - 10585.0 - 504000.0, $row['profit'], 'V Profit = S−U−Y sesuai workbook');

        $unmatched = $service->unmatchedShipments(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
        $this->assertSame(1, $unmatched['count']);
        $this->assertSame('MR-RKP-2', $unmatched['items']->first()->tracking_id);
    }
}
