<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master resi + riwayat status + pemetaan status agregator
 * (prompt.md §6/§7; audit STAGE1 §7). Menggantikan DBMengantar + OutputResi.
 * Nomor disimpan sebagai STRING; status terkini diturunkan dari events (bukan ditimpa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_status_mappings', function (Blueprint $t) {
            $t->id();
            $t->enum('platform', ['mengantar', 'lincah', 'general'])->default('general');
            $t->string('status_system');                 // C "Status System" (mis. DELIVERED)
            $t->enum('status_internal', ['packing', 'dikirim', 'undel', 'diterima', 'retur']);
            $t->string('keterangan')->nullable();        // E
            $t->boolean('is_active')->default(true);
            $t->date('effective_from')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['platform', 'status_system']);
        });

        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('platform', ['mengantar', 'lincah']);
            $t->string('platform_order_id', 64)->nullable();   // B Order ID
            $t->string('tracking_id', 64)->nullable();         // C Tracking ID / no AWB
            $t->string('return_resi', 64)->nullable();         // D Resi Forward/R
            $t->string('expedition', 32)->nullable();          // A
            $t->string('customer_name')->nullable();           // E
            $t->string('customer_phone', 32)->index();         // F (ternormalisasi string)
            $t->text('address')->nullable();                   // G (nyata s/d ±400 karakter — jangan varchar)
            $t->string('province')->nullable();                // H
            $t->string('city')->nullable();                    // I
            $t->string('district')->nullable();                // J
            $t->string('subdistrict')->nullable();             // Y
            $t->string('zip_code', 12)->nullable();            // K
            $t->decimal('cod_value', 15, 2)->default(0);       // L
            $t->decimal('product_value', 15, 2)->default(0);   // M
            $t->string('goods_desc')->nullable();              // N
            $t->unsignedInteger('quantity')->default(1);       // O
            $t->timestamp('create_date')->nullable();          // P
            $t->timestamp('last_update')->nullable();          // Q
            $t->string('status_raw', 64)->nullable();          // R status asli platform
            $t->enum('status_internal', ['packing', 'dikirim', 'undel', 'diterima', 'retur'])->nullable();
            $t->string('last_pod_status')->nullable();         // S
            $t->decimal('shipping_fee', 15, 2)->default(0);    // T
            $t->decimal('shipping_discount', 15, 2)->default(0); // U
            $t->decimal('cod_fee', 15, 2)->default(0);         // V (Inc VAT)
            $t->decimal('return_fee', 15, 2)->default(0);      // W
            $t->string('remark', 64)->nullable();              // X
            $t->string('remark_corrected', 64)->nullable();    // AB (diberi awalan 0)
            $t->string('adv_resi_code', 8)->nullable();        // AE
            $t->string('cs_resi_code', 8)->nullable();         // AF
            $t->string('product_resi_code', 4)->nullable();    // AG
            $t->string('campaign_name', 64)->nullable();       // AH
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('duplicate_count')->default(0); // AI Cek double
            $t->timestamps();
            $t->unique(['platform', 'tracking_id']);
            $t->index(['platform', 'platform_order_id']);
            $t->index(['status_internal', 'create_date']);
        });

        Schema::create('shipment_status_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $t->string('status_raw', 64)->nullable();
            $t->enum('status_internal', ['packing', 'dikirim', 'undel', 'diterima', 'retur'])->nullable();
            $t->string('pod_status')->nullable();
            $t->timestamp('status_date')->nullable();          // Q Last Update
            $t->decimal('shipping_fee', 15, 2)->nullable();
            $t->decimal('shipping_discount', 15, 2)->nullable();
            $t->decimal('cod_fee', 15, 2)->nullable();
            $t->decimal('return_fee', 15, 2)->nullable();
            $t->foreignId('import_row_id')->nullable();        // sumber baris impor
            $t->timestamps();
            $t->index(['shipment_id', 'status_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('carrier_status_mappings');
    }
};
