<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log untuk koreksi data & tindakan finansial (prompt.md §4.6, §3, §12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');                         // order.override, status.change, commission.close, ...
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->text('reason')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamps();
            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
