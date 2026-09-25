<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CsAgent;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\MarketingDailyReport;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CommissionRuleSeeder;
use Database\Seeders\ReferenceMasterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dashboard (prompt.md §9): filter lengkap, kartu konsisten dengan tabel detail
 * (angka kartu = agregat kumpulan resi/laporan yang sama), dan gerbang peran halaman Tahap 5.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ReferenceMasterSeeder::class);
        $this->seed(CommissionRuleSeeder::class);

        $this->superadmin = User::create([
            'name' => 'Superadmin', 'email' => 'super@arj.test',
            'password' => bcrypt('x'), 'admin_input_code' => 91, 'is_active' => true,
        ]);
        $this->superadmin->roles()->attach(Role::where('slug', 'superadmin')->value('id'));
    }

    private function shipment(string $tracking, array $over = []): Shipment
    {
        return Shipment::create(array_merge([
            'platform'          => 'mengantar',
            'tracking_id'       => $tracking,
            'customer_phone'    => '628130000000',
            'status_internal'   => 'diterima',
            'cod_value'         => 200000,
            'shipping_fee'      => 22000,
            'quantity'          => 1,
            'create_date'       => '2026-06-01 08:00:00',
            'expedition'        => 'JNE',
            'adv_resi_code'     => 'A1',
            'cs_resi_code'      => 'C1',
            'product_resi_code' => 'PG',
        ], $over));
    }

    private function marketingReport(): void
    {
        $campaign = Campaign::create([
            'name' => 'AM01-GL01-PG', 'is_mapped' => true,
            'advertiser_id' => \App\Models\Advertiser::where('code', 'AM01')->value('id'),
            'cs_agent_id'   => CsAgent::where('lookup_key', 'GL01')->value('id'),
            'product_id'    => Product::where('code', 'P001')->value('id'),
        ]);
        MarketingDailyReport::create([
            'campaign_id' => $campaign->id, 'periode' => '2606',
            'date_start' => '2026-06-01', 'date_end' => '2026-06-01',
            'spend_raw' => 100000, 'spend_ppn' => 112000,
        ]);
    }

    public function test_dashboard_cards_match_financial_detail(): void
    {
        // s1 diterima (laba eksplisit 65.255), s2 dikirim (laba eksplisit 29.003,75).
        $this->shipment('DASH-1');
        $this->shipment('DASH-2', ['status_internal' => 'dikirim', 'cod_value' => 150000, 'create_date' => '2026-06-02 08:00:00']);
        $this->marketingReport();

        $res = $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30');
        $res->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('shipmentTotal', 2)
            ->where('counts.diterima', 1)
            ->where('counts.dikirim', 1)
            ->where('withoutStatus', 0)
            // Perbandingan numerik lewat (float) cast: JSON round-trip bisa mengubah
            // 350000.0 → int 350000 bila serialize_precision ≠ -1 di environment.
            ->where('totals.nilai_penjualan', fn ($v) => (float) $v === 350000.0)                    // 200.000 + 150.000
            ->where('totals.ongkir', fn ($v) => (float) $v === 44000.0)                              // 22.000 × 2
            ->where('totals.laba_eksplisit', fn ($v) => (float) $v === 65255.0 + 29003.75)
            ->where('totals.komisi_cs', fn ($v) => (float) $v === 10585.0 + (-1498.75))
            ->where('totals.admin_input', fn ($v) => (float) $v === 1000.0)
            ->where('totals.spend', fn ($v) => (float) $v === 112000.0)
            ->where('totals.profit', fn ($v) => (float) $v === 65255.0 + 29003.75 - (10585.0 - 1498.75) - 112000.0) // laba − komisi CS − spend (V=S−U−Y)
            ->has('spendRows', 1)
            ->where('spendRows.0.spend_ppn', fn ($v) => (float) $v === 112000.0) // Detail spend = baris laporan yang sama dengan kartu.
        );
    }

    public function test_dashboard_filters_narrow_cards_and_detail(): void
    {
        $this->shipment('FILT-1');
        $this->shipment('FILT-2', ['status_internal' => 'dikirim', 'create_date' => '2026-06-02 08:00:00']);
        $this->shipment('FILT-3', ['platform' => 'lincah', 'expedition' => 'SICEPAT']);

        $res = $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30&status=diterima');
        $res->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shipmentTotal', 2)
            ->where('counts.diterima', 2)
            ->where('counts.dikirim', 0)
        );

        $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30&platform=lincah')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('shipmentTotal', 1));

        $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30&expedition=SICEPAT')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('shipmentTotal', 1));

        // Filter ADV memakai atribusi kode resi (A1 = ADV AM01).
        $adv = \App\Models\Advertiser::where('code', 'AM01')->value('id');
        $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30&advertiser_id=' . $adv)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('shipmentTotal', 3));

        // Filter CS: C1 = GL01 (Ani).
        $cs = CsAgent::where('lookup_key', 'GL01')->value('id');
        $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30&cs_agent_id=' . $cs)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('shipmentTotal', 3));

        // Filter produk: PG = P001.
        $product = Product::where('code', 'P001')->value('id');
        $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30&product_id=' . $product)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('shipmentTotal', 3));
    }

    public function test_dashboard_order_classification_cards(): void
    {
        $customer = Customer::create(['name' => 'Test', 'phone_raw' => '0812', 'phone_normalized' => '6281200999']);
        foreach (['positif', 'positif', 'negatif'] as $i => $cls) {
            Order::create([
                'reference_code' => 'DASHORD-' . $i . '-0001',
                'order_date' => '2026-06-01',
                'customer_id' => $customer->id,
                'customer_phone_normalized' => $customer->phone_normalized,
                'classification' => $cls,
            ]);
        }

        $res = $this->actingAs($this->superadmin)->get('/dashboard?from=2026-06-01&to=2026-06-30');
        $res->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('orderTotal', 3)
            ->where('orderCounts.positif', 2)
            ->where('orderCounts.negatif', 1)
        );
    }

    public function test_stage5_pages_are_reachable_for_superadmin(): void
    {
        $this->actingAs($this->superadmin)->get('/marketing')->assertOk();
        $this->actingAs($this->superadmin)->get('/rekap-adv')->assertOk();
        $this->actingAs($this->superadmin)->get('/komisi')->assertOk();
        $this->actingAs($this->superadmin)->get('/aturan-komisi')->assertOk();
    }

    public function test_marketing_pages_restricted_to_marketing_role(): void
    {
        $adminOrder = User::create([
            'name' => 'Admin Order', 'email' => 'ao@arj.test',
            'password' => bcrypt('x'), 'admin_input_code' => 1, 'is_active' => true,
        ]);
        $adminOrder->roles()->attach(Role::where('slug', 'admin_order')->value('id'));

        // Admin order TIDAK boleh membuka marketing/komisi (§3 matriks peran).
        $this->actingAs($adminOrder)->get('/marketing')->assertForbidden();
        $this->actingAs($adminOrder)->get('/komisi')->assertForbidden();
        // Rekap ADV boleh untuk finance_owner; admin_order tidak.
        $this->actingAs($adminOrder)->get('/rekap-adv')->assertForbidden();
    }

    public function test_import_batch_platform_marketing_is_stored(): void
    {
        ImportBatch::create([
            'batch_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'platform' => 'marketing', 'source_filename' => 'x.csv',
            'uploaded_by' => $this->superadmin->id, 'status' => 'preview', 'total_rows' => 0,
        ]);

        $this->assertDatabaseHas('import_batches', ['platform' => 'marketing']);
    }
}
