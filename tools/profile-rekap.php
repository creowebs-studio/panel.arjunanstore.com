<?php

// Profil komponen /rekap-adv via API publik.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Marketing\RekapAdvService;
use Illuminate\Support\Carbon;

$svc = app(RekapAdvService::class);
$from = Carbon::parse('2026-09-01')->startOfDay();
$to = Carbon::parse('2026-09-25')->endOfDay();

$t = microtime(true);
$shipments = $svc->rangeShipments($from, $to);
printf("rangeShipments        : %.3fs (%d resi)\n", microtime(true) - $t, $shipments->count());

$t = microtime(true);
$rows = $svc->rows($from, $to, null, null, $shipments);
$total = array_sum(array_map(fn ($r) => $r['total'], $rows));
printf("rows                  : %.3fs (%d baris, %d resi tercocok)\n", microtime(true) - $t, count($rows), $total);

$t = microtime(true);
$unmatched = $svc->unmatchedShipments($from, $to, 50, $shipments);
printf("unmatchedShipments    : %.3fs (%d tak cocok)\n", microtime(true) - $t, $unmatched['count']);
