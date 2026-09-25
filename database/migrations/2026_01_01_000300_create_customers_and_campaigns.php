<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelanggan, kampanye, dan laporan harian iklan (prompt.md §8/§10; audit STAGE1 §8).
 * Nomor telepon disimpan sebagai STRING ternormalisasi (pertahankan nol di depan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();               // D "Nama Customer"
            $t->string('phone_raw', 32);                  // E apa adanya
            $t->string('phone_normalized', 32)->unique(); // AJ (62…, tanpa -/spasi)
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();                 // C "Nama Kampanye" (AMxx-ARyy-PZ)
            $t->string('adv_code', 8)->nullable();        // LEFT(name,4/5)
            $t->string('cs_code', 12)->nullable();        // REGEXEXTRACT "-(.*?)-"
            $t->string('product_resi_code', 4)->nullable(); // RIGHT(name,2)
            $t->foreignId('advertiser_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('cs_agent_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('platform', 24)->default('meta');  // sumber export (Meta Ads)
            $t->string('delivery_status', 24)->nullable(); // D "Penayangan" (active/archived)
            $t->string('attribution', 128)->nullable();  // E pengaturan atribusi
            $t->string('result_indicator', 128)->nullable(); // G "Indikator Hasil"
            $t->boolean('is_mapped')->default(false);     // berhasil dipetakan ADV/CS/Produk
            $t->timestamps();
        });

        Schema::create('marketing_daily_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $t->char('periode', 4);                       // TEXT(tgl,"YYMM")
            $t->date('date_start');                       // A "Awal Pelaporan"
            $t->date('date_end');                         // B "Akhir Pelaporan"
            $t->unsignedBigInteger('results')->default(0);      // F Hasil
            $t->decimal('cost_per_result', 15, 2)->default(0);  // J
            $t->decimal('budget_set', 15, 2)->default(0);       // K Anggaran Set Iklan
            $t->string('budget_type', 24)->nullable();          // L
            $t->decimal('spend_raw', 15, 2)->default(0);        // M Jumlah Dibelanjakan
            $t->decimal('spend_ppn', 15, 2)->default(0);        // = spend_raw * 1.12 (audit §8.2)
            $t->unsignedBigInteger('reach')->default(0);        // H Jangkauan
            $t->decimal('frequency', 10, 2)->default(0);        // I
            $t->unsignedBigInteger('impressions')->default(0);  // O
            $t->decimal('cpm', 15, 2)->default(0);              // P
            $t->unsignedBigInteger('clicks_link')->default(0);  // Q
            $t->decimal('cpc_link', 15, 2)->default(0);         // R
            $t->decimal('ctr_link', 8, 4)->default(0);          // S
            $t->unsignedBigInteger('clicks_all')->default(0);   // T
            $t->decimal('ctr_all', 8, 4)->default(0);           // U
            $t->decimal('cpc_all', 15, 2)->default(0);          // V
            $t->string('source_file', 191)->nullable();
            $t->foreignId('import_batch_id')->nullable();      // FK ditambahkan belakangan (tabel impor)
            $t->timestamps();
            $t->unique(['campaign_id', 'date_start', 'date_end']);
            $t->index(['periode', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_daily_reports');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('customers');
    }
};
