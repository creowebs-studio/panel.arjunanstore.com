<?php

namespace App\Services;

/**
 * Normalisasi nomor telepon — mereplikasi `Input!AJ` (audit STAGE1 §4.2) PERSIS.
 *
 * Aturan (berurutan, pada string mentah E):
 *  - buang semua "-" dan spasi,
 *  - jika awal "62"          → pertahankan,
 *  - jika awal "0"           → "62" + MID(E,2,15)   (buang 0 depan),
 *  - jika awal bukan 0/62    → "62" + E,
 *  simpan sebagai STRING (jangan angka; nol depan dijaga oleh awalan 62).
 */
class PhoneNormalizer
{
    public function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-]/u', '', $raw);
        $digits = preg_replace('/[^0-9]/u', '', $digits ?? '');

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1, 15);
        }

        return '62' . $digits;
    }

    /** Format tampilan "0xxx-xxxx-xxxx" dari AJ (padanan `Input!Z`) agar ramah operator. */
    public function display(?string $normalized): ?string
    {
        if (!$normalized) {
            return null;
        }

        // 62 8xx xxxx xxxx → 08xx-xxxx-xxxx
        if (str_starts_with($normalized, '62')) {
            $local = '0' . substr($normalized, 2);
        } else {
            $local = $normalized;
        }

        $len = strlen($local);
        if ($len <= 4) {
            return $local;
        }

        $head = substr($local, 0, 4);
        $rest = substr($local, 4);
        $chunks = trim((string) preg_replace('/(.{4})/', '$1-', $rest), '-');

        return $chunks === '' ? $head : $head . '-' . $chunks;
    }
}
