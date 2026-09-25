<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles & permissions (prompt.md §3). Enforcement is done in the backend
 * (middleware + policies), including for exports and financial data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();          // superadmin, admin_order, admin_pengiriman, marketing_adv, finance_owner
            $t->string('name');
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();          // orders.input, orders.export, shipments.import, commissions.view, ...
            $t->string('name');
            $t->string('group')->nullable();       // Modul pengelompok (order/pengiriman/keuangan/sistem)
            $t->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'user_id']);
        });

        // Profil operasional tambahan pada tabel users bawaan framework.
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone')->nullable();
            // Padanan "Kode Admin Input" (Input!AI1) yang menjadi prefiks kode resi.
            $t->unsignedTinyInteger('admin_input_code')->nullable()->unique();
            $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['phone', 'admin_input_code', 'is_active']);
        });
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
