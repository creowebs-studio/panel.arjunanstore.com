<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ExportBatch;
use App\Models\Order;
use App\Models\OrderValidation;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClassificationRuleSeeder;
use Database\Seeders\ReferenceMasterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Alur ujung-ke-ujung Tahap 3 lewat HTTP: login → gerbang peran → input order →
 * klasifikasi → daftar → ekspor (hanya positif & lengkap). Melindungi kriteria #1,#2,#9,#11.
 */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ReferenceMasterSeeder::class);
        $this->seed(ClassificationRuleSeeder::class);
    }

    /** Kode admin input unik per peran (kolom users.admin_input_code ber-unique index). */
    private const INPUT_CODES = [
        'superadmin' => 91, 'admin_order' => 1, 'admin_pengiriman' => 2,
        'marketing_adv' => 3, 'finance_owner' => 4,
    ];

    private function userWithRole(string $slug): User
    {
        $user = User::create([
            'name'             => ucfirst($slug) . ' User',
            'email'            => $slug . '@arj.test',
            'password'         => bcrypt('secret'),
            'admin_input_code' => self::INPUT_CODES[$slug] ?? 99,
            'is_active'        => true,
        ]);
        $user->roles()->attach(Role::where('slug', $slug)->value('id'));

        return $user->refresh();
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'order_date'     => '2026-06-01',
            'customer_name'  => 'Rina',
            'phone'          => '0812-3456-7890',
            'address_detail' => 'Jl. Melati No. 5',
            'kelurahan'      => 'Mekarjaya',
            'kecamatan'      => 'Sukmajaya',
            'provinsi'       => 'Jawa Barat',
            'zip_code'       => '16411',
            'product_id'     => Product::where('code', 'P001')->value('id'),
            'product_detail' => 'Sepatu Running',
            'qty'            => 1,
            'payment_method' => 'COD',
            'price'          => 159000,
            'aggregator'     => 'mengantar',
        ], $overrides);
    }

    public function test_login_screen_is_reachable_for_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_order_list_requires_authentication(): void
    {
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_admin_order_can_input_order_and_it_is_classified_positif(): void
    {
        $this->actingAs($this->userWithRole('admin_order'));

        $res = $this->post('/orders', $this->orderPayload());
        $res->assertRedirect(route('orders.index'));
        $res->assertSessionHas('order_result');

        $order = Order::firstWhere('customer_phone_normalized', '6281234567890');
        $this->assertNotNull($order);
        $this->assertSame('positif', $order->classification); // nomor tanpa riwayat → positif
        $this->assertNotEmpty($order->reference_code);
    }

    public function test_marketing_adv_cannot_input_order(): void
    {
        $this->actingAs($this->userWithRole('marketing_adv'));

        $this->post('/orders', $this->orderPayload())->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_export_includes_only_positive_and_complete_orders(): void
    {
        $admin = $this->userWithRole('admin_order');
        $product = Product::where('code', 'P001')->first();

        // 1) positif + lengkap → HARUS ikut
        $ok = $this->makeOrder('628111000101', 'positif', $product, lengkap: true, ref: 'EXPORTOK0001');
        // 2) positif tapi alamat kurang → TIDAK ikut
        $this->makeOrder('628111000102', 'positif', $product, lengkap: false, ref: 'EXPORTMIN0002');
        // 3) negatif lengkap → TIDAK ikut
        $this->makeOrder('628111000103', 'negatif', $product, lengkap: true, ref: 'EXPORTNEG0003');

        $res = $this->actingAs($admin)->get('/ekspor/mengantar');
        $res->assertOk();
        $csv = $res->getContent();

        $this->assertStringContainsString($ok->reference_code, $csv);
        $this->assertStringNotContainsString('EXPORTMIN0002', $csv);
        $this->assertStringNotContainsString('EXPORTNEG0003', $csv);

        $this->assertDatabaseHas('export_batches', ['platform' => 'mengantar', 'order_count' => 1]);
        $this->assertSame(1, ExportBatch::first()->items()->count());
    }

    public function test_order_detail_page_shows_timeline_and_sources(): void
    {
        $admin = $this->userWithRole('admin_order');
        $order = $this->makeOrder('628111000200', 'positif', Product::where('code', 'P001')->first(), lengkap: true, ref: 'DETAILOK0001');

        $res = $this->actingAs($admin)->get(route('orders.show', $order));
        $res->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Show')
            ->where('order.reference_code', 'DETAILOK0001')
            ->has('order.validations')
            ->has('order.audit_logs')
            ->has('shipments')
            ->where('exportable', true)
        );
    }

    public function test_override_changes_classification_and_is_audited(): void
    {
        $admin = $this->userWithRole('admin_order');
        $order = $this->makeOrder('628111000201', 'positif', Product::where('code', 'P001')->first(), lengkap: true, ref: 'OVERRIDE0001');

        $res = $this->actingAs($admin)->post(route('orders.override', $order), [
            'classification' => 'negatif',
            'reason'         => 'Pelanggan membatalkan lewat WhatsApp',
        ]);
        $res->assertRedirect(); // kembali ke halaman detail

        $order->refresh();
        $this->assertSame('negatif', $order->classification);
        $this->assertTrue($order->is_manual_override);
        $this->assertSame($admin->id, $order->override_by);

        // Koreksi tercatat: jejak validasi + audit log (prompt.md §4.6).
        $this->assertSame(1, OrderValidation::where('order_id', $order->id)->whereJsonContains('evidence->manual', true)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'order.classification_override',
            'auditable_id' => $order->id,
            'user_id'      => $admin->id,
        ]);
        $this->assertSame('positif', AuditLog::first()->old_values['classification']);
    }

    public function test_override_requires_permission_and_reason(): void
    {
        $order = $this->makeOrder('628111000202', 'positif', Product::where('code', 'P001')->first(), lengkap: true, ref: 'OVERRIDE0002');

        // Peran tanpa izin koreksi → 403.
        $this->actingAs($this->userWithRole('marketing_adv'))
            ->post(route('orders.override', $order), ['classification' => 'negatif', 'reason' => 'coba-coba'])
            ->assertForbidden();

        // Alasan wajib (min 5 karakter); klasifikasi tidak berubah.
        $this->actingAs($this->userWithRole('admin_order'))
            ->post(route('orders.override', $order), ['classification' => 'negatif', 'reason' => 'x'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('positif', $order->fresh()->classification);
    }

    private function makeOrder(string $phone, string $classification, Product $product, bool $lengkap, string $ref): Order
    {
        $customer = Customer::create([
            'phone_normalized' => $phone,
            'phone_raw'        => $phone,
            'name'             => 'Pelanggan ' . substr($phone, -3),
        ]);

        return Order::create([
            'reference_code'            => $ref,
            'order_date'                => '2026-06-01',
            'customer_id'               => $customer->id,
            'customer_phone_normalized' => $phone,
            'product_id'                => $product->id,
            'product_detail'            => 'Sepatu Running',
            'qty'                       => 1,
            'payment_method'            => 'COD',
            'price'                     => 159000,
            'aggregator'                => 'mengantar',
            'classification'            => $classification,
            'address_detail'            => $lengkap ? 'Jl. Uji No. 1' : null,
            'kelurahan'                 => $lengkap ? 'Mekarjaya' : null,
            'kecamatan'                 => $lengkap ? 'Sukmajaya' : null,
            'admin_input_code'          => 1,
        ]);
    }
}
