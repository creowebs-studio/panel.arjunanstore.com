<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Indeks untuk rekap ADV↔resi & dashboard: pencarian per rentang tanggal + kode resi/kampanye. */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            $t->index('create_date');
            $t->index('campaign_id');
            $t->index('adv_resi_code');
            $t->index('cs_resi_code');
            $t->index('product_resi_code');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            $t->dropIndex(['create_date']);
            $t->dropIndex(['campaign_id']);
            $t->dropIndex(['adv_resi_code']);
            $t->dropIndex(['cs_resi_code']);
            $t->dropIndex(['product_resi_code']);
        });
    }
};
