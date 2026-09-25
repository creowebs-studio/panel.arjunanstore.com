<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 5 peran wajib (prompt.md §3/§4) + permission granular per modul + akun superadmin awal.
 * Enforcement di backend via middleware 'role'/'permission' dan Policy.
 */
class RolePermissionSeeder extends Seeder
{
    /** slug role => [permission slug yang dimiliki] */
    private const ROLE_PERMISSIONS = [
        'superadmin' => ['*'], // bypass di model, tapi tetap simbolik

        'admin_order' => [
            'orders.view', 'orders.input', 'orders.edit', 'orders.classify',
            'orders.export', 'customers.view', 'customers.edit',
        ],

        'admin_pengiriman' => [
            'orders.view', 'shipments.import', 'shipments.view', 'shipments.correct_status',
            'carriers.mapping.manage', 'data_issues.view', 'data_issues.resolve',
            'exports.view',
        ],

        'marketing_adv' => [
            'campaigns.view', 'campaigns.import', 'reports.view', 'reports.adv',
            'commissions.view_adv', 'dashboards.view',
        ],

        'finance_owner' => [
            'commissions.view', 'commissions.calculate', 'commissions.close_period',
            'commissions.pay', 'reports.view', 'reports.finance', 'dashboards.view',
            'audit.view',
        ],
    ];

    /** Permission yang belum dimiliki role mana pun tetap dibuat agar siap ditugaskan. */
    private const ALL_PERMISSIONS = [
        'orders.view' => ['Input & daftar order', 'order'],
        'orders.input' => ['Input order baru', 'order'],
        'orders.edit' => ['Koreksi order', 'order'],
        'orders.classify' => ['Override positif/negatif', 'order'],
        'orders.export' => ['Ekspor order positif', 'order'],
        'customers.view' => ['Daftar customer', 'order'],
        'customers.edit' => ['Koreksi customer', 'order'],
        'shipments.import' => ['Impor hasil platform', 'pengiriman'],
        'shipments.view' => ['Daftar resi/shipment', 'pengiriman'],
        'shipments.correct_status' => ['Koreksi status internal', 'pengiriman'],
        'carriers.mapping.manage' => ['Kelola pemetaan status', 'pengiriman'],
        'data_issues.view' => ['Lihat data error', 'pengiriman'],
        'data_issues.resolve' => ['Selesaikan data error', 'pengiriman'],
        'exports.view' => ['Lihat riwayat ekspor', 'pengiriman'],
        'campaigns.view' => ['Daftar kampanye', 'marketing'],
        'campaigns.import' => ['Impor data iklan', 'marketing'],
        'reports.view' => ['Laporan umum', 'laporan'],
        'reports.adv' => ['Rekap ADV', 'laporan'],
        'reports.finance' => ['Laporan keuangan', 'laporan'],
        'commissions.view' => ['Lihat komisi', 'keuangan'],
        'commissions.view_adv' => ['Lihat komisi ADV', 'keuangan'],
        'commissions.calculate' => ['Hitung komisi', 'keuangan'],
        'commissions.close_period' => ['Tutup periode', 'keuangan'],
        'commissions.pay' => ['Catat pembayaran', 'keuangan'],
        'dashboards.view' => ['Dashboard', 'sistem'],
        'audit.view' => ['Jejak audit', 'sistem'],
        'users.manage' => ['Kelola user & peran', 'sistem'],
    ];

    public function run(): void
    {
        // Pastikan semua permission dikenal ada di tabel TERLEBIH DAHULU —
        // superadmin menyinkronkan seluruh permission, jadi urutan ini wajib
        // (pada DB segar, pluck('id') di bawah akan kosong bila dilewati).
        foreach (array_keys(self::ALL_PERMISSIONS) as $slug) {
            $this->permission($slug);
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $perms) {
            $role = Role::firstOrCreate(
                ['slug' => $roleSlug],
                ['name' => ucwords(str_replace('_', ' ', $roleSlug))]
            );

            if ($perms === ['*']) {
                $role->permissions()->syncWithoutDetaching(
                    Permission::pluck('id')->all()
                );
                continue;
            }

            $ids = collect($perms)
                ->map(fn ($slug) => $this->permission($slug))
                ->pluck('id')
                ->all();
            $role->permissions()->syncWithoutDetaching($ids);
        }

        // Akun superadmin awal (kredensial default — wajib diganti di produksi).
        $admin = User::firstOrCreate(
            ['email' => 'superadmin@arj.test'],
            [
                'name'      => 'Super Admin ARJ',
                'password'  => Hash::make('change-me-please'),
                'is_active' => true,
            ]
        );
        $super = Role::where('slug', 'superadmin')->first();
        if ($super) {
            $admin->roles()->syncWithoutDetaching([$super->id]);
        }
    }

    private function permission(string $slug): Permission
    {
        [$name, $group] = self::ALL_PERMISSIONS[$slug] ?? [ucwords(str_replace('.', ' ', $slug)), null];

        return Permission::firstOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
    }
}
