<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * View agregat riwayat per nomor telepon ternormalisasi — pengganti 'Perform by wa'
 * (audit STAGE1 §3.6/§4.3). Menjadi masukan aturan positif/negatif (Input!AM/AN/AO/AK).
 *
 * Definisi status:
 *  - "sudah diterima"  : status_internal = diterima
 *  - "pernah retur"    : status_internal = retur
 *  - "masih diproses"  : status_internal in (packing, dikirim)  → padanan AM (in-progress)
 *  - "order terakhir"  : event status terbaru per nomor (untuk AK = RETUR ? Negatif)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW v_phone_history AS
            SELECT
                s.customer_phone                                                        AS phone_normalized,
                COUNT(*)                                                                AS total_shipments,
                SUM(s.status_internal = 'diterima')                                     AS terima_count,
                SUM(s.status_internal = 'retur')                                        AS retur_count,
                SUM(s.status_internal IN ('packing','dikirim'))                         AS in_progress_count,
                MAX(s.create_date)                                                      AS last_create_date,
                SUBSTRING_INDEX(GROUP_CONCAT(s.status_internal ORDER BY s.create_date DESC, s.id DESC SEPARATOR '|'), '|', 1) AS last_status_internal,
                SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(s.tracking_id,'')       ORDER BY s.create_date DESC, s.id DESC SEPARATOR '|'), '|', 1) AS last_resi
            FROM shipments s
            WHERE s.customer_phone IS NOT NULL AND s.customer_phone <> ''
            GROUP BY s.customer_phone
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_phone_history');
    }
};
