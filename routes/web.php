<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CarrierMappingController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataIssueController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/orders');

// ---- Tamu -------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.attempt');
});

// ---- Terautentikasi ---------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('role:superadmin,finance_owner,marketing_adv,admin_order,admin_pengiriman')
        ->name('dashboard');

    // Daftar order dapat dilihat banyak peran; input/ekspor khusus Admin Order (+superadmin).
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/baru', [OrderController::class, 'create'])
        ->middleware('role:superadmin,admin_order')->name('orders.create');
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware('role:superadmin,admin_order')->name('orders.store');
    // Detail diletakkan SETELAH 'orders/baru' agar tidak tertutup wildcard.
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/koreksi', [OrderController::class, 'override'])
        ->middleware('role:superadmin,admin_order')->name('orders.override');

    Route::get('ekspor', [ExportController::class, 'index'])
        ->middleware('role:superadmin,admin_order,admin_pengiriman')->name('exports.index');
    Route::get('ekspor/{platform}', [ExportController::class, 'download'])
        ->middleware('role:superadmin,admin_order')->name('exports.download');

    // ---- Master resi (Alur D) ----------------------------------------
    Route::get('resi', [ShipmentController::class, 'index'])
        ->middleware('role:superadmin,admin_pengiriman,admin_order')->name('shipments.index');
    Route::get('resi/{shipment}', [ShipmentController::class, 'show'])
        ->middleware('role:superadmin,admin_pengiriman,admin_order')->name('shipments.show');

    // ---- Impor hasil platform (Alur C) --------------------------------
    Route::get('impor', [ImportController::class, 'index'])
        ->middleware('role:superadmin,admin_pengiriman')->name('imports.index');
    Route::post('impor', [ImportController::class, 'store'])
        ->middleware('role:superadmin,admin_pengiriman')->name('imports.store');
    Route::get('impor/{batch}', [ImportController::class, 'preview'])
        ->middleware('role:superadmin,admin_pengiriman')->name('imports.preview');
    Route::post('impor/{batch}/proses', [ImportController::class, 'process'])
        ->middleware('role:superadmin,admin_pengiriman')->name('imports.process');
    Route::post('impor/{batch}/proses-ulang', [ImportController::class, 'reprocess'])
        ->middleware('role:superadmin,admin_pengiriman')->name('imports.reprocess');

    // ---- Pemetaan status agregator (prompt.md §7: dapat ditinjau admin) --
    Route::get('pemetaan-status', [CarrierMappingController::class, 'index'])
        ->middleware('permission:carriers.mapping.manage')->name('carrier-mappings.index');
    Route::post('pemetaan-status', [CarrierMappingController::class, 'store'])
        ->middleware('permission:carriers.mapping.manage')->name('carrier-mappings.store');
    Route::patch('pemetaan-status/{mapping}', [CarrierMappingController::class, 'update'])
        ->middleware('permission:carriers.mapping.manage')->name('carrier-mappings.update');
    Route::post('pemetaan-status/sinkron', [CarrierMappingController::class, 'sync'])
        ->middleware('permission:carriers.mapping.manage')->name('carrier-mappings.sync');

    // ---- Data Error ---------------------------------------------------
    Route::get('data-error', [DataIssueController::class, 'index'])
        ->middleware('role:superadmin,admin_pengiriman')->name('issues.index');
    Route::patch('data-error/{issue}', [DataIssueController::class, 'update'])
        ->middleware('role:superadmin,admin_pengiriman')->name('issues.update');

    // ---- Marketing: impor kampanye & rekap ADV (Alur E) ----------------
    Route::middleware('role:superadmin,marketing_adv')->group(function () {
        Route::get('marketing', [MarketingController::class, 'index'])->name('marketing.index');
        Route::post('marketing/impor', [MarketingController::class, 'store'])->name('marketing.store');
        Route::get('marketing/impor/{batch}', [MarketingController::class, 'preview'])->name('marketing.preview');
        Route::post('marketing/impor/{batch}/proses', [MarketingController::class, 'process'])->name('marketing.process');
        Route::post('marketing/kampanye/{campaign}/petakan', [MarketingController::class, 'updateCampaign'])
            ->name('marketing.campaign.update');
    });
    Route::get('rekap-adv', [MarketingController::class, 'rekap'])
        ->middleware('role:superadmin,marketing_adv,finance_owner')->name('marketing.rekap');

    // ---- Komisi CS/ADV (Alur F) ----------------------------------------
    Route::middleware('role:superadmin,finance_owner')->group(function () {
        Route::get('komisi', [CommissionController::class, 'index'])->name('commissions.index');
        Route::post('komisi/periode', [CommissionController::class, 'store'])->name('commissions.store');
        Route::get('komisi/periode/{period}', [CommissionController::class, 'show'])->name('commissions.show');
        Route::post('komisi/periode/{period}/hitung-ulang', [CommissionController::class, 'recompute'])
            ->name('commissions.recompute');
        Route::post('komisi/periode/{period}/bayar', [CommissionController::class, 'storePayment'])
            ->name('commissions.pay');
        Route::post('komisi/periode/{period}/tutup', [CommissionController::class, 'close'])
            ->name('commissions.close');

        Route::get('aturan-komisi', [CommissionRuleController::class, 'index'])->name('commission-rules.index');
        Route::post('aturan-komisi', [CommissionRuleController::class, 'store'])->name('commission-rules.store');
    });
});
