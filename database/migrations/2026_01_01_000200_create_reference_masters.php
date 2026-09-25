<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master referensi (prompt.md §10; audit STAGE1 §3).
 * Sumber: DB PRODUK, DB ADVS&CS, ADV (Master ARJ / file master eksternal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();                 // D "Kode"
            $t->string('resi_code', 4)->unique();         // P "Kode Produk" (kode resi 2 char, mis. PG)
            $t->string('category')->nullable();           // C Kategori
            $t->string('name');                           // E Nama Produk
            $t->string('jenis_pesanan')->nullable()->index(); // padanan J "Jenis Pesanan" utk lookup AF
            $t->decimal('price_sell_pcs', 15, 2)->default(0); // F Harga Jual (Pc)
            $t->decimal('hpp', 15, 2)->default(0);        // G HPP
            $t->decimal('packing_cost', 15, 2)->default(0); // H Packing
            $t->unsignedInteger('qty_per_paket')->default(1); // I Qty/Paket
            $t->decimal('total_hpp', 15, 2)->default(0);  // J Total HPP
            $t->decimal('ops_cost', 15, 2)->default(0);   // K Biaya Operasional
            $t->decimal('komisi_cs_input', 15, 2)->default(0); // L Komisi CS & Input
            $t->decimal('cogs', 15, 2)->default(0);       // M COGS
            $t->decimal('price_sell_paket', 15, 2)->default(0); // N Harga Jual/Paket
            $t->decimal('margin_per_product', 15, 2)->default(0); // O Margin/Produk
            // Daftar "Harga Jual Min" per kuantitas (L:U pada DB PRODUK) — INDEX by qty.
            $t->json('min_price_by_qty')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('advertisers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 8)->unique();              // B "Kode ADVS" (AM01, AM02, …)
            $t->string('name');                           // C "Nama ADVS"
            $t->string('email')->nullable();              // D
            $t->string('resi_code', 8)->nullable();       // H "Kode Resi" utk komposisi kode resi
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('cs_agents', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();             // C "Kode CS"
            $t->string('lookup_key')->unique();           // E "Kode Nama CS" (kunci XLOOKUP Input!AD/AE)
            $t->string('name');                           // D "Nama CS"
            $t->string('resi_cs_code', 8)->nullable();    // I kode CS pada komposisi resi
            $t->foreignId('advertiser_id')->nullable()->constrained()->nullOnDelete(); // F/G
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_agents');
        Schema::dropIfExists('advertisers');
        Schema::dropIfExists('products');
    }
};
