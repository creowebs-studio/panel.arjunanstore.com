<?php

namespace Tests\Feature;

use App\Models\CarrierStatusMapping;
use App\Models\DataIssue;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CarrierStatusMappingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Peninjauan pemetaan status (prompt.md §7): hanya peran ber-izin
 * `carriers.mapping.manage` yang bisa membuka & mengubah; perubahan ber-audit.
 */
class CarrierMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(CarrierStatusMappingSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::create([
            'name' => ucfirst($slug), 'email' => $slug . '@arj.test',
            'password' => bcrypt('secret'), 'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('slug', $slug)->value('id'));

        return $user->refresh();
    }

    public function test_admin_pengiriman_can_review_mapping_page(): void
    {
        $res = $this->actingAs($this->userWithRole('admin_pengiriman'))->get('/pemetaan-status');
        $res->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('CarrierMappings/Index')
            ->has('mappings')
            ->whereContains('mappings', fn (array $m) => ($m['status_system'] ?? null) === 'ON DELIVERY') // dari seeder
            ->has('internal', 5)
        );
    }

    public function test_role_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole('admin_order'))->get('/pemetaan-status')->assertForbidden();
    }

    public function test_mapping_can_be_updated_and_is_audited(): void
    {
        $user = $this->userWithRole('admin_pengiriman');
        // Seeder mengisi pemetaan platform 'general' (berlaku lintas agregator).
        $mapping = CarrierStatusMapping::where('platform', 'general')->where('status_system', 'ON DELIVERY')->first();
        $this->assertNotNull($mapping);

        $this->actingAs($user)
            ->patch(route('carrier-mappings.update', $mapping), [
                'status_internal' => 'undel',
                'keterangan'      => 'Salah petakan, diperbaiki',
                'is_active'       => '1',
            ])
            ->assertRedirect();

        $this->assertSame('undel', $mapping->fresh()->status_internal);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'carrier_mapping.updated',
            'user_id' => $user->id,
        ]);
    }

    public function test_sync_applies_new_mapping_to_existing_shipments(): void
    {
        $user = $this->userWithRole('admin_pengiriman');

        // Resi lama: status mentah CANCELLED (belum punya pemetaan) + issue terbuka.
        $shipment = Shipment::create([
            'platform' => 'mengantar', 'tracking_id' => 'SYNC-1',
            'status_raw' => 'CANCELLED', 'status_internal' => null,
            'customer_phone' => '628111000300',
        ]);
        DataIssue::create([
            'type' => 'status_unmapped', 'shipment_id' => $shipment->id,
            'message' => 'Status "CANCELLED" belum dipetakan', 'status' => 'open',
        ]);

        // Admin menambah pemetaan, lalu sinkronkan ke resi lama.
        $this->actingAs($user)->post('/pemetaan-status', [
            'platform' => 'general', 'status_system' => 'CANCELLED', 'status_internal' => 'undel',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('carrier-mappings.sync'))->assertRedirect();

        $this->assertSame('undel', $shipment->fresh()->status_internal);
        $this->assertDatabaseHas('data_issues', [
            'shipment_id' => $shipment->id, 'type' => 'status_unmapped', 'status' => 'resolved',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'carrier_mapping.sync']);
    }

    public function test_new_mapping_can_be_added_but_duplicate_is_rejected(): void
    {
        $user = $this->userWithRole('admin_pengiriman');

        $this->actingAs($user)->post('/pemetaan-status', [
            'platform' => 'mengantar', 'status_system' => 'CANCELLED',
            'status_internal' => 'undel', 'keterangan' => 'Dibatalkan',
        ])->assertRedirect();

        $this->assertDatabaseHas('carrier_status_mappings', [
            'platform' => 'mengantar', 'status_system' => 'CANCELLED', 'status_internal' => 'undel',
        ]);

        // Duplikat ditolak dengan pesan kesalahan (bukan baris baru).
        $this->actingAs($user)->post('/pemetaan-status', [
            'platform' => 'mengantar', 'status_system' => 'CANCELLED', 'status_internal' => 'retur',
        ])->assertSessionHasErrors('status_system');
        $this->assertSame(1, CarrierStatusMapping::where('status_system', 'CANCELLED')->count());
    }
}
