<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder orkestrasi (dipanggil `php artisan migrate:fresh --seed` pada first-run entrypoint).
 * Urutan penting: peran/permission & user dulu (dipakai reviewed_by/approved_by FK),
 * lalu master referensi, pemetaan status, dan tarif komisi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ReferenceMasterSeeder::class,
            CarrierStatusMappingSeeder::class,
            CommissionRuleSeeder::class,
            ClassificationRuleSeeder::class,
        ]);
    }
}
