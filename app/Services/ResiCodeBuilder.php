<?php

namespace App\Services;

use App\Models\Order;

/**
 * Pembentuk kode resi/remark `Input!AH` (audit STAGE1 §4.2):
 *   DDMM(tgl) + ADMIN("00") + KodeADV + KodeCS + KodeProduk + NO(urut "0000")
 *
 * Kode 14‑char ini kelak di‑parse balik saat impor resi (Tahap 4) untuk atribusi ADV/CS/Produk.
 */
class ResiCodeBuilder
{
    /**
     * Susun reference_code. $seq adalah nomor urut harian (mulai 1) → nol‑depan 4 digit.
     */
    public function build(
        string $dateYmd,
        ?int $adminInputCode,
        ?string $advCode,
        ?string $csCode,
        ?string $productCode,
        int $seq
    ): string {
        $ts   = \DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd) ?: new \DateTimeImmutable($dateYmd);
        $mm   = $ts->format('m');
        $dd   = $ts->format('d');

        return implode('', [
            $dd . $mm,                       // AB TEXT(tgl,"DDMM")
            str_pad((string) ($adminInputCode ?? 1), 2, '0', STR_PAD_LEFT), // AC "00"
            $this->tok($advCode, 2),        // AD Kode ADV
            $this->tok($csCode, 2),         // AE Kode CS
            $this->tok($productCode, 2),    // AF Kode Produk
            str_pad((string) $seq, 4, '0', STR_PAD_LEFT), // AG "0000"
        ]);
    }

    /** Ambil bagian komposisi dari relasi order (adv/cs/product resi code). */
    public function partsFor(Order $order): array
    {
        return [
            'adv'     => $order->adv_resi_code ?? $order->campaign?->advertiser?->resi_code,
            'cs'      => $order->cs_resi_code ?? $order->csAgent?->resi_cs_code,
            'product' => $order->product_resi_code ?? $order->product?->resi_code,
            'admin'   => $order->admin_input_code,
        ];
    }

    /** Nomor urut harian berikutnya untuk tanggal order (menghindari tabrakan reference_code). */
    public function nextSequence(string $dateYmd): int
    {
        $today = Order::withTrashed()->whereDate('order_date', $dateYmd)->count();

        return $today + 1;
    }

    private function tok(?string $v, int $width): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return str_repeat('X', $width);
        }

        return substr(strtoupper($v), 0, $width);
    }
}
