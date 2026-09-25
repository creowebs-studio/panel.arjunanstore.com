<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pembentuk baris ekspor order positif (prompt.md §5; audit STAGE1 §5.1/§5.2).
 * Hanya order classification=positif + data wajib lengkap + sesuai agregator yang diekspor.
 * Semua teks uppercase mengikuti workbook.
 */
class OrderExporter
{
    public const INSTRUCTION = 'MOHON MAAF TANPA VIDEO UNBOXING KOMPLAIN KERUSAKAN/KEKURANGAN BARANG TIDAK DITERIMA!!!';

    public function headers(string $platform): array
    {
        return $platform === 'lincah'
            ? ['Nama', 'Alamat', 'No Telp', 'Kode Pos', 'Kurir', 'Tipe Pengiriman', 'Berat',
               'Harga Non-COD', 'Nilai COD', 'Isi Paket', 'Jumlah Barang', 'Remark1', 'Instruksi']
            : ['Nama Penerima', 'Alamat', 'No Telp', 'District', 'Subdistrict', 'Kode Pos', 'Berat',
               'Harga NON-COD', 'Nilai COD', 'Isi Paket', 'Remark1', 'Instruksi', 'Provinsi'];
    }

    /** @param Collection<int,Order> $orders */
    public function rows(string $platform, Collection $orders): array
    {
        return $orders->map(fn (Order $o) => $platform === 'lincah' ? $this->lincahRow($o) : $this->mengantarRow($o))
            ->all();
    }

    private function mengantarRow(Order $o): array
    {
        return [
            $this->up($o->customer?->name),
            $this->up($this->fullAddress($o)),
            $o->customer_phone_normalized,
            $this->up($o->kecamatan),
            $this->up($o->kelurahan),
            $o->zip_code,
            1,                                   // Berat konstan (§5.1)
            $o->payment_method === 'NON COD' ? $o->price : '',
            $o->payment_method === 'COD' ? $o->price : '',
            $this->up($this->isiPaket($o)),
            $o->reference_code,                  // Remark1 = kode resi (AH)
            self::INSTRUCTION,
            $this->up($o->provinsi),
        ];
    }

    private function lincahRow(Order $o): array
    {
        return [
            $this->up($o->customer?->name),
            $this->up($this->fullAddress($o)),
            $o->customer_phone_normalized,
            $o->zip_code,
            $this->up($o->expedition),           // Kurir
            $this->up($o->payment_method),       // Tipe Pengiriman
            1,
            $o->payment_method === 'NON COD' ? $o->price : '',
            $o->payment_method === 'COD' ? $o->price : '',
            $this->up($this->isiPaket($o)),
            $o->qty,                             // Jumlah Barang
            $o->reference_code,
            self::INSTRUCTION,
        ];
    }

    /** CSV siap unduh (dipisah ";" ala export Indonesia, BOM UTF‑8 utk Excel). */
    public function toCsv(string $platform, Collection $orders): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $this->headers($platform), ';');
        foreach ($this->rows($platform, $orders) as $row) {
            fputcsv($fh, $row, ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return "\xEF\xBB\xBF" . $csv;
    }

    private function isiPaket(Order $o): string
    {
        $isi = trim(str_ireplace('PCS ', '', (string) $o->product_detail));

        return $isi . ' ' . $o->qty . ' PCS';
    }

    private function fullAddress(Order $o): string
    {
        return collect([
            $o->address_detail, $o->kelurahan, $o->kecamatan, $o->kabupaten, $o->kota, $o->provinsi, $o->zip_code,
        ])->filter()->unique()->implode(', ');
    }

    private function up($v)
    {
        return is_string($v) ? mb_strtoupper($v) : $v;
    }
}
