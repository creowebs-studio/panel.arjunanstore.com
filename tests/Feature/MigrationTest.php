<?php

namespace Tests\Feature;

use App\Models\Advertiser;
use App\Models\Campaign;
use App\Models\CsAgent;
use App\Models\MarketingDailyReport;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CarrierStatusMappingSeeder;
use Database\Seeders\CommissionRuleSeeder;
use Database\Seeders\ReferenceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Tahap 6 — skrip migrasi data awal dari workbook (masters, DBMengantar, Rekap marketing)
 * + rekonsiliasi OutputResi. Fixture CSV meniru keluaran tools/xlsx-to-csv.ps1.
 */
class MigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommissionRuleSeeder::class);
        $this->seed(ReferenceMasterSeeder::class);
        $this->seed(CarrierStatusMappingSeeder::class);
        User::create([
            'name' => 'Admin', 'email' => 'admin@arj.test',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $this->dir = sys_get_temp_dir().'/arj-migrasi-'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function putCsv(string $name, string $content): string
    {
        $path = $this->dir.'/'.$name;
        File::put($path, $content);

        return $path;
    }

    /** Dua baris ADV_CS nyata (AM01/GL01 + AM02/AR01) dan tiga produk (PR/HR/PG). */
    private function writeAdvCs(): string
    {
        return $this->putCsv('adv_cs.csv', implode("\n", [
            'No,Kode CS,Nama CS,Kode Nama CS,Kode ADV,Nama ADV,ADV,CS,Kode CS',
            '1,GL01,AINUN,GL01-AINUN,AM01,GILANG,1,01,101',
            '6,AR01,AINUNN,AR01-AINUNN,AM02,ARIF,2,01,201',
        ])."\n");
    }

    private function writeProduk(): string
    {
        return $this->putCsv('produk.csv', implode("\n", [
            'No,Kategory,Kode Produk,Kode Resi Produk,Nama Produk,HPP Produk,Biaya Packing,Biaya OPS,Komisi CS,COGS,QTT/ Paket,L,M,N,O,P,Q,R,S,T,U',
            '1.,Item,PR,11.0,PEWARNA RAMBUT,20000.0,1000.0,2000.0,500.0,23500,1.0,89000.0,169000.0,,,,,,,,',
            '2.,Item,HR,12.0,MADU,20000.0,1000.0,2000.0,500.0,23500,1.0,89000.0,169000.0,,,,,,,,',
            '4.,Item,PG,14.0,PASTA GIGI,20000.0,1000.0,2000.0,500.0,23500.0,1.0,89000.0,169000.0,,,,,,,,',
        ])."\n");
    }

    public function test_migrate_masters_upsert_dan_idempoten(): void
    {
        $advcs = $this->writeAdvCs();
        $produk = $this->writeProduk();

        $this->artisan('arj:migrate-masters', ['--advcs' => $advcs, '--produk' => $produk])
            ->assertSuccessful();

        $this->assertSame('ARIF', Advertiser::where('code', 'AM02')->value('name'));
        $cs = CsAgent::where('lookup_key', 'AR01')->first();
        $this->assertNotNull($cs);
        $this->assertSame('AR01', $cs->code);          // kode ikut workbook, agar cocok token kampanye
        $this->assertSame('AR01', $cs->resi_cs_code);  // agar cocok avec adv_resi/cs_resi shipment migrasi
        $this->assertSame(Advertiser::where('code', 'AM02')->value('id'), $cs->advertiser_id);

        $prod = Product::where('resi_code', 'PR')->first();
        $this->assertNotNull($prod);
        $this->assertSame('11', $prod->code);                          // "11.0" → "11"
        $this->assertSame('PEWARNA RAMBUT', $prod->name);
        $this->assertEquals(20000.0, (float) $prod->hpp);
        $this->assertSame(89000.0, $prod->minPriceForQty(1));
        $this->assertSame(169000.0, $prod->minPriceForQty(2));

        // Jalankan ulang → idempoten (tanpa duplikasi).
        $before = [Advertiser::count(), CsAgent::count(), Product::count()];
        $this->artisan('arj:migrate-masters', ['--advcs' => $advcs, '--produk' => $produk])
            ->assertSuccessful();
        $this->assertSame($before, [Advertiser::count(), CsAgent::count(), Product::count()]);
    }

    public function test_migrate_shipments_memeta_remark_numerik_dan_idempoten(): void
    {
        $advcs = $this->writeAdvCs();
        $produk = $this->writeProduk();

        // Baris nyata DBMengantar #2: X=40301201140022, AB=040301201140022 → CS "201"→AR01, produk "14"→PG.
        $db = $this->putCsv('dbmengantar.csv', implode("\n", [
            'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,AB,AC,AD,AE,AF,AG,AH,AI,AJ,AK,AL',
            'JNE,260405CBU0WH,196642600497898,,MBAH KYAI MUKTI,628133713772,JL. TROPODO JAYA,JAWA TIMUR,SIDOARJO,WARU,61256,116000.0,116000.0,PASTA GIGI 1 PCS,1.0,2026-04-05 12:21:00,2026-04-08 20:00:00,DELIVERED,Paket telah diterima,11000.0,400.0,3863.0,,40301201140022,WEDORO,040301201140022,DITERIMA,0.0,AM02-ARIF,AR01-AINUNN,PG,AM02-AR01-PG,0.0,46120.83333,DELIVERED,Paket telah diterima',
        ])."\n");

        // Kampanye sudah dimigrasikan lebih dulu (untuk backfill campaign_id).
        Campaign::create(['name' => 'AM02-AR01-PG', 'platform' => 'meta']);

        $this->artisan('arj:migrate-shipments', ['--file' => $db, '--advcs' => $advcs, '--produk' => $produk])
            ->assertSuccessful();

        $s = Shipment::where('tracking_id', '196642600497898')->first();
        $this->assertNotNull($s);
        $this->assertSame('mengantar', $s->platform);
        $this->assertSame('40301201140022', $s->remark);
        $this->assertSame('040301201140022', $s->remark_corrected);
        $this->assertSame('AM02', $s->adv_resi_code);       // token 201 → ADV_CS!E
        $this->assertSame('AR01', $s->cs_resi_code);        // token 201 → ADV_CS!B
        $this->assertSame('PG', $s->product_resi_code);     // token 14 → Produk!C
        $this->assertSame('AM02-AR01-PG', $s->campaign_name);
        $this->assertSame('diterima', $s->status_internal); // DELIVERED → DITERIMA
        $this->assertNotNull($s->campaign_id);              // backfill kampanye
        $this->assertSame(1, $s->statusEvents()->count());

        // Impor ulang file yang sama → duplicate, bukan gandakan.
        $this->artisan('arj:migrate-shipments', ['--file' => $db, '--advcs' => $advcs, '--produk' => $produk])
            ->assertSuccessful();
        $this->assertSame(1, Shipment::where('tracking_id', '196642600497898')->count());
        $this->assertSame(1, \App\Models\ImportBatch::where('source_type', 'migration')->orderByDesc('id')->value('duplicate_rows'));
    }

    public function test_migrate_campaigns_header_rekap_nyata(): void
    {
        $rekap = $this->putCsv('rekap.csv', implode("\n", [
            'Awal Pelaporan,Akhir Pelaporan,Nama Kampanye,Penayangan Kampanye,Pengaturan atribusi,Hasil,Indikator Hasil,Jangkauan,Frekuensi,Biaya per Hasil,Anggaran Set Iklan,Jenis Anggaran Set Iklan,Jumlah yang dibelanjakan (IDR),Berakhir,Impresi,CPM (Biaya Per 1.000 Tayangan) (IDR),Klik Tautan,CPC (Biaya per Klik Tautan) (IDR),CTR (Rasio Klik Tayang Tautan),Klik (Semua),CTR (Semua),CPC (Semua) (IDR)',
            '2026-03-31,2026-03-31,AM01-GL01-PG,archived,"Klik 7 hari",2,actions:purchase,1086,1.044199,14467.5,100000,Harian,28935,2026-04-01,1134,25515.87302,24,1205.625,2.116402,36,3.174603,803.75',
        ])."\n");

        $this->artisan('arj:migrate-campaigns', ['--file' => $rekap])->assertSuccessful();

        $c = Campaign::where('name', 'AM01-GL01-PG')->first();
        $this->assertNotNull($c);
        $this->assertTrue((bool) $c->is_mapped);
        $this->assertSame('archived', $c->delivery_status);           // alias header nyata
        $report = MarketingDailyReport::where('campaign_id', $c->id)->first();
        $this->assertNotNull($report);
        // Pemetaan membulatkan ke 2 desimal (round), jadi 25515.87302 → 25515.87.
        $this->assertEqualsWithDelta(25515.87, (float) $report->cpm, 0.01); // alias "CPM (Biaya Per …)"
        $this->assertEqualsWithDelta(28935 * 1.12, (float) $report->spend_ppn, 0.01);

        // Impor ulang → duplicate.
        $this->artisan('arj:migrate-campaigns', ['--file' => $rekap])->assertSuccessful();
        $this->assertSame(1, MarketingDailyReport::where('campaign_id', $c->id)->count());
    }

    public function test_reconcile_membandingkan_buku_dan_website(): void
    {
        // Resi RETUR persis contoh baris OutputResi (baris 4 buku: AK=-6625.8, AM=73996.8).
        $product = Product::create([
            'code' => '11', 'resi_code' => 'PR', 'name' => 'PEWARNA RAMBUT',
            'hpp' => 20000, 'packing_cost' => 1000, 'ops_cost' => 2000,
            'min_price_by_qty' => ['1' => 89000.0],
        ]);
        Shipment::create([
            'platform' => 'mengantar', 'tracking_id' => 'T-COCOK',
            'customer_phone' => '6281234567890',
            'cod_value' => 124000, 'product_value' => 124000, 'quantity' => 1,
            'shipping_fee' => 33129, 'shipping_discount' => 400,
            'status_raw' => 'RTS', 'status_internal' => 'retur',
            'create_date' => '2026-03-30 00:00:00', 'product_resi_code' => $product->resi_code,
        ]);

        $letters = 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,AA,AB,AC,AD,AE,AF,AG,AH,AI,AJ,AK,AL,AM,AN,AO,AP';
        $row = fn (string $awb, array $overrides) => implode(',', array_map(
            fn ($l) => $overrides[$l] ?? ($l === 'A' ? $awb : ''),
            explode(',', $letters)
        ));
        $csv = $this->putCsv('outputresi.csv', $letters."\n"
            .$row('T-COCOK', ['E' => 'AM02-ARIF', 'F' => 'AR01-AINUNN', 'AD' => '23000', 'V' => '32729', 'AG' => '-6625.8', 'AJ' => '0', 'AK' => '-6625.8', 'AM' => '73996.8'])."\n"
            .$row('T-HILANG', ['E' => 'XXXX', 'AK' => '100', 'AM' => '200'])."\n");

        $out = $this->dir.'/laporan.md';
        $this->artisan('arj:reconcile', ['--file' => $csv, '--limit' => 10, '--out' => $out])
            ->assertSuccessful();

        $report = File::get($out);
        $this->assertStringContainsString('Rekonsiliasi Tahap 6', $report);
        $this->assertStringContainsString('**1**', $report);            // 1 cocok persis
        $this->assertStringContainsString('T-HILANG', $report);         // tidak ditemukan dilaporkan
        $this->assertStringContainsString('73.996,80', $report);        // ΣAM buku = ΣAM web (Δ nol)
    }
}
