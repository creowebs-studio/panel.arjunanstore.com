<?php

// Cek cepat: jumlah permission superadmin (harus > 0 setelah seeder).
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::where('email', 'superadmin@arj.test')->first();
if (! $u) {
    echo "superadmin: TIDAK ADA\n";
    exit(1);
}
$slugs = $u->permissionSlugs();
echo 'superadmin permission: '.count($slugs)."\n";
echo 'memiliki carriers.mapping.manage: '.($u->hasPermission('carriers.mapping.manage') ? 'YA' : 'TIDAK')."\n";
