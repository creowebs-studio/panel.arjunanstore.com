<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komisi CS & ADV (prompt.md §9; audit STAGE1 §9).
 * - commission_rules: tarif bertanggal-berlaku (effective) agar periode tertutup tak berubah (§9.1/§9.2).
 * - commission_periods: memisahkan PERIODE CS (16–15) dari PERIODE ADV (bulan kalender) — §9.4.
 * - commission_entries: komponen order/transfer/ongkir/… per resi (OutputResi AG:AK).
 * - commission_payments: pencatatan tanggal/jumlah/referensi pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $t) {
            $t->id();
            $t->string('key');                          // order_tier | retur_penalty | transfer_bonus | ongkir_pct | admin_input | cod_rate | ppn_cod_rate | margin_pct_below_setup
            $t->string('applies_to')->default('cs');    // cs | adv | shipment
            $t->string('name');
            $t->json('params');                         // mis. order_tier: [{max:26000,amount:0},{max:89000,amount:5000},{min:99001,percent:10}]
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['key', 'effective_from']);
        });

        Schema::create('commission_periods', function (Blueprint $t) {
            $t->id();
            $t->enum('owner_type', ['cs', 'adv']);
            $t->unsignedBigInteger('owner_id');         // cs_agents.id / advertisers.id
            $t->string('label');                        // "CS MEI 2026"
            $t->enum('period_type', ['cs_16_15', 'calendar_month', 'custom']);
            $t->date('start_date');
            $t->date('end_date');
            $t->enum('status', ['open', 'closed'])->default('open');
            $t->decimal('carried_balance', 15, 2)->default(0); // Saldo Komisi Periode Lalu (Z)
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->unique(['owner_type', 'owner_id', 'start_date', 'end_date'], 'commission_periods_uq');
        });

        Schema::create('commission_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commission_period_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('component', ['order', 'transfer', 'ongkir', 'multi_paket', 'admin_input', 'retur_penalty']);
            $t->enum('status_internal', ['packing', 'dikirim', 'undel', 'diterima', 'retur'])->nullable();
            $t->boolean('is_on_progress')->default(false);   // On Progress vs Closed (§9.3)
            $t->boolean('is_payable')->default(false);
            $t->decimal('amount', 15, 2)->default(0);
            $t->foreignId('rule_version_id')->nullable()->constrained()->nullOnDelete();
            $t->json('rule_snapshot')->nullable();           // parameter saat dihitung (auditability)
            $t->timestamp('computed_at')->nullable();
            $t->timestamps();
            $t->index(['commission_period_id', 'component']);
        });

        Schema::create('commission_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commission_period_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('owner_type', ['cs', 'adv']);
            $t->unsignedBigInteger('owner_id');
            $t->date('paid_date');
            $t->decimal('amount', 15, 2);
            $t->string('reference')->nullable();             // Payment ACC ref
            $t->string('method', 32)->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_periods');
        Schema::dropIfExists('commission_rules');
    }
};
