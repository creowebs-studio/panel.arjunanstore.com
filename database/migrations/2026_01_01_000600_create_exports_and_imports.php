<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ekspor order positif + impor hasil platform + data error
 * (prompt.md §5/§6; audit STAGE1 §5/§6/§7.3).
 * Idempotensi impor dijaga via unique key + kolom result pada import_rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_batches', function (Blueprint $t) {
            $t->id();
            $t->uuid('batch_uuid')->unique();
            $t->enum('platform', ['mengantar', 'lincah']);
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('filename')->nullable();
            $t->enum('status', ['prepared', 'downloaded', 'reexported'])->default('prepared');
            $t->unsignedInteger('order_count')->default(0);
            $t->boolean('is_reexport')->default(false);        // ditandai agar tak diproses ganda
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['platform', 'status']);
        });

        Schema::create('export_batch_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['export_batch_id', 'order_id']);
        });

        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->uuid('batch_uuid')->unique();
            // 'marketing' = impor laporan kampanye Meta Ads (prompt.md §8 / audit §8.1).
            $t->enum('platform', ['mengantar', 'lincah', 'marketing']);
            $t->string('source_type', 24)->default('file');    // file/api
            $t->string('source_filename');
            $t->string('stored_path')->nullable();             // penyimpanan privat file asli
            $t->string('checksum', 64)->nullable();            // deteksi file sama diimpor ulang
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['reading', 'validated', 'preview', 'processing', 'done', 'failed'])->default('reading');
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('new_rows')->default(0);
            $t->unsignedInteger('updated_rows')->default(0);
            $t->unsignedInteger('duplicate_rows')->default(0);
            $t->unsignedInteger('error_rows')->default(0);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->string('tracking_id', 64)->nullable();
            $t->string('platform_order_id', 64)->nullable();
            $t->json('raw_data');                              // data asli baris
            $t->json('mapped_data')->nullable();              // hasil pemetaan
            $t->enum('result', ['new', 'update', 'duplicate', 'error', 'not_found', 'unknown_status'])->default('new');
            $t->string('error_reason')->nullable();
            $t->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['import_batch_id', 'row_number']);
            $t->index(['tracking_id']);
        });

        Schema::create('data_issues', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['status_unmapped', 'remark_unmapped', 'double_resi', 'required_missing', 'format_invalid', 'unknown_status', 'not_found', 'campaign_unmapped', 'metric_normalized']);
            $t->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('import_row_id')->nullable()->constrained()->nullOnDelete();
            $t->text('message'); // laporan worklist bisa panjang (daftar agregat — Tahap 6)
            $t->json('payload')->nullable();
            $t->enum('status', ['open', 'resolved', 'ignored'])->default('open');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'status']);
        });

        // Tautkan shipment_status_events → import_rows (setelah tabel ada).
        Schema::table('shipment_status_events', function (Blueprint $t) {
            $t->foreign('import_row_id')->references('id')->on('import_rows')->nullOnDelete();
        });
        // Tautkan marketing_daily_reports → import_batches.
        Schema::table('marketing_daily_reports', function (Blueprint $t) {
            $t->foreign('import_batch_id')->references('id')->on('import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_daily_reports', function (Blueprint $t) {
            $t->dropForeign(['import_batch_id']);
        });
        Schema::table('shipment_status_events', function (Blueprint $t) {
            $t->dropForeign(['import_row_id']);
        });
        Schema::dropIfExists('data_issues');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('export_batch_items');
        Schema::dropIfExists('export_batches');
    }
};
