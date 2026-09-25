<?php

namespace Database\Seeders;

use App\Models\Advertiser;
use App\Models\CsAgent;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * CONTOH master referensi agar aplikasi bisa langsung dicoba (prompt.md Tahap 2 "seed data referensi").
 * ⚠️ BUKAN data produksi — master asli ada di file eksternal (audit STAGE1 §2 / U2):
 *    "DB PRODUK", "DB ADVS&CS", dan Google Sheet ADV_CS/Produk. Ganti seeder ini dengan
 *    importer saat file master sebenarnya tersedia.
 *
 * Kode resi disusun dari: KodeADV(resi_code) + KodeCS(resi_cs_code) + KodeProduk(resi_code),
 * sesuai pola parse MID() pada Input/DBMengantar (§4.2/§7.1).
 */
class ReferenceMasterSeeder extends Seeder
{
    public function run(): void
    {
        // Kumpulkan sebagai Collection (jangan ->all(), agar firstWhere() tersedia di bawah).
        $adv = collect([
            ['code' => 'AM01', 'name' => 'ADV Contoh Satu',  'resi_code' => 'A1'],
            ['code' => 'AM02', 'name' => 'ADV Contoh Dua',   'resi_code' => 'A2'],
        ])->map(fn ($a) => Advertiser::updateOrCreate(['code' => $a['code']], $a));

        // CS terhubung ke ADV. lookup_key = kolom B "Kode CS" tab ADV_CS workbook
        // (pola nyata GL01/AR01) — token tengah nama kampanye `AMxx-ARyy-PZ` hasil REGEXEXTRACT"-(.*?)-".
        $csRows = [
            ['code' => 'CS001', 'lookup_key' => 'GL01', 'name' => 'Ani',  'resi_cs_code' => 'C1', 'adv' => 'AM01'],
            ['code' => 'CS002', 'lookup_key' => 'GL02', 'name' => 'Budi', 'resi_cs_code' => 'C2', 'adv' => 'AM01'],
            ['code' => 'CS003', 'lookup_key' => 'AR01', 'name' => 'Cici', 'resi_cs_code' => 'C3', 'adv' => 'AM02'],
        ];
        foreach ($csRows as $row) {
            CsAgent::updateOrCreate(
                ['code' => $row['code']],
                [
                    'lookup_key'    => $row['lookup_key'],
                    'name'          => $row['name'],
                    'resi_cs_code'  => $row['resi_cs_code'],
                    'advertiser_id' => $adv->firstWhere('code', $row['adv'])?->id,
                    'is_active'     => true,
                ]
            );
        }

        // Contoh produk (min_price_by_qty: peta kuantitas → harga jual minimum, INDEX L:U §7.4/L).
        $products = [
            [
                'code' => 'P001', 'resi_code' => 'PG', 'category' => 'Sepatu',
                'name' => 'Sepatu Running ARJ', 'jenis_pesanan' => 'COD',
                'price_sell_pcs' => 159000, 'hpp' => 90000, 'packing_cost' => 5000,
                'qty_per_paket' => 1, 'price_sell_paket' => 159000,
                'min_price_by_qty' => ['1' => 149000, '2' => 289000, '3' => 420000],
            ],
            [
                'code' => 'P002', 'resi_code' => 'BS', 'category' => 'Baju',
                'name' => 'Baju Polo ARJ', 'jenis_pesanan' => 'Transfer',
                'price_sell_pcs' => 89000, 'hpp' => 45000, 'packing_cost' => 3000,
                'qty_per_paket' => 1, 'price_sell_paket' => 89000,
                'min_price_by_qty' => ['1' => 79000, '2' => 150000],
            ],
        ];
        foreach ($products as $p) {
            Product::updateOrCreate(['code' => $p['code']], $p + ['is_active' => true]);
        }
    }
}
