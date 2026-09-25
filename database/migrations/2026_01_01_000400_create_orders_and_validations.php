<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order + hasil validasi positif/negatif (prompt.md §4; audit STAGE1 §4).
 * rule_versions menyimpan versi aturan agar hasil lama dapat ditelusuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_versions', function (Blueprint $t) {
            $t->id();
            $t->string('name');                       // mis. "Klasifikasi No Telepon Positif/Negatif"
            $t->string('version');                    // v1
            $t->json('definition');                   // decision tree AK/AM/AN/AO/AP/AQ (audit §4.3)
            $t->date('effective_from');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference_code', 32)->unique();     // AH "NO RESI/REMARK" (DDMM+AI+ADV+CS+PROD+urut)
            $t->date('order_date');                         // B
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('customer_phone_normalized', 32)->index(); // salinan AJ utk agregasi riwayat
            $t->foreignId('cs_agent_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Alamat
            $t->string('address_detail')->nullable();       // F
            $t->string('kelurahan')->nullable();            // H / U
            $t->string('kecamatan')->nullable();            // V
            $t->string('kabupaten')->nullable();            // G bagian ke-2
            $t->string('kota')->nullable();                 // W
            $t->string('provinsi')->nullable();             // X
            $t->string('zip_code', 12)->nullable();         // I
            // Isi order
            $t->string('product_detail')->nullable();       // K "Detail Pesanan"
            $t->unsignedInteger('qty')->default(1);         // L
            $t->enum('payment_method', ['COD', 'NON COD'])->default('COD'); // M
            $t->decimal('price', 15, 2)->default(0);        // N
            $t->decimal('weight', 8, 2)->default(0);        // O berat paket
            $t->enum('aggregator', ['mengantar', 'lincah'])->default('mengantar'); // P
            $t->string('expedition', 32)->nullable();       // Q (Lincah: kurir)
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            // Atribusi komposisi kode
            $t->unsignedTinyInteger('admin_input_code')->nullable();  // AC dari AI1
            $t->string('adv_resi_code', 8)->nullable();     // AD
            $t->string('cs_resi_code', 8)->nullable();      // AE
            $t->string('product_resi_code', 4)->nullable(); // AF
            // Validasi (Alur A)
            $t->enum('classification', ['pending', 'positif', 'negatif', 'perlu_ditinjau'])->default('pending');
            $t->foreignId('rule_version_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('validated_at')->nullable();
            // Koreksi manual (ber-audit)
            $t->boolean('is_manual_override')->default(false);
            $t->string('override_reason')->nullable();
            $t->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('override_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['classification', 'order_date']);
            $t->index(['aggregator', 'order_date']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name')->nullable();
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('price', 15, 2)->default(0);
            $t->timestamps();
        });

        // Riwayat hasil validasi (dapat ditelusuri; evidence lengkap).
        Schema::create('order_validations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rule_version_id')->nullable()->constrained()->nullOnDelete();
            $t->string('result', 24);                  // Output Data Positif/Negatif
            $t->string('by_wa', 24)->nullable();       // AP: Negatif/Positif/Data Belum Tersedia
            $t->string('last_order_status', 24)->nullable(); // AK: Positif/Negatif
            $t->string('in_progress_resi', 64)->nullable();  // AM
            $t->unsignedInteger('retur_count')->default(0);  // AN
            $t->unsignedInteger('terima_count')->default(0); // AO
            $t->text('reason')->nullable();
            $t->json('evidence')->nullable();
            $t->timestamp('checked_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_validations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('rule_versions');
    }
};
