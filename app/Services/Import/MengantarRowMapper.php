<?php

namespace App\Services\Import;

use App\Models\CarrierStatusMapping;
use App\Services\PhoneNormalizer;

/**
 * Memetakan SATU baris mentah hasil platform → field shipment ternormalisasi (audit STAGE1 §7.1).
 * Murni & mudah diuji. Nomor/ID dipertahankan sebagai STRING. Status mentah diterjemahkan lewat
 * CarrierStatusMapping; bila tak ada pemetaan → status_internal null (nanti jadi Data Error).
 */
class MengantarRowMapper
{
    /** Alias header (huruf kecil, tanpa spasi ganda) → kunci kanonik. */
    private const ALIASES = [
        'expedition' => 'expedition', 'kurir' => 'expedition', 'shipping provider' => 'expedition',
        'order id' => 'platform_order_id', 'orderid' => 'platform_order_id', 'no order' => 'platform_order_id',
        'tracking id' => 'tracking_id', 'no awb' => 'tracking_id', 'resi' => 'tracking_id', 'tracking' => 'tracking_id',
        'resi forward/r' => 'return_resi', 'return resi' => 'return_resi', 'resi retur' => 'return_resi',
        'customer name' => 'customer_name', 'nama' => 'customer_name', 'nama penerima' => 'customer_name',
        'phone' => 'phone', 'no telp' => 'phone', 'telephone' => 'phone', 'no wa' => 'phone', 'nomor' => 'phone',
        'address' => 'address', 'alamat' => 'address',
        'province' => 'province', 'provinsi' => 'province',
        'city' => 'city', 'kota' => 'city', 'kabupaten' => 'city',
        'district' => 'district', 'kecamatan' => 'district',
        'subdistrict' => 'subdistrict', 'kelurahan' => 'subdistrict',
        'zip' => 'zip_code', 'kode pos' => 'zip_code', 'zip code' => 'zip_code',
        'cod' => 'cod_value', 'nilai cod' => 'cod_value',
        'product value' => 'product_value', 'harga' => 'product_value',
        'goods desc' => 'goods_desc', 'isi paket' => 'goods_desc',
        'qty' => 'quantity', 'jumlah' => 'quantity', 'jumlah barang' => 'quantity',
        'create date' => 'create_date', 'tgl order' => 'create_date',
        'last update' => 'last_update', 'tgl update' => 'last_update',
        'status' => 'status_raw', 'last status' => 'status_raw', 'status system' => 'status_raw',
        'status agregator' => 'status_raw',
        'pod status' => 'last_pod_status', 'last pod status' => 'last_pod_status',
        'shipping fee' => 'shipping_fee', 'biaya ongkir' => 'shipping_fee', 'ongkir' => 'shipping_fee',
        'shipping discount' => 'shipping_discount', 'diskon ongkir' => 'shipping_discount',
        'cod fee' => 'cod_fee', 'biaya cod' => 'cod_fee',
        'return fee' => 'return_fee', 'biaya retur' => 'return_fee',
        'remark1' => 'remark', 'remark' => 'remark',
    ];

    public function __construct(private readonly PhoneNormalizer $normalizer)
    {
    }

    /**
     * @param array<string,string> $raw
     * @return array<string,mixed>
     */
    public function map(array $raw, string $platform): array
    {
        $r = $this->normalizeKeys($raw);
        $get = fn (string $k) => trim((string) ($r[$k] ?? ''));

        $remark = $get('remark');
        [$adv, $cs, $prod] = $this->parseRemark($remark);

        $statusRaw = $get('status_raw');
        $statusInternal = CarrierStatusMapping::internalFor($platform, $statusRaw !== '' ? $statusRaw : null);

        return [
            'platform'          => $platform,
            'expedition'        => $get('expedition') ?: null,
            'platform_order_id' => $get('platform_order_id') ?: null,
            'tracking_id'       => $get('tracking_id') ?: null,
            'return_resi'       => $get('return_resi') ?: null,
            'customer_name'     => $get('customer_name') ?: null,
            'customer_phone'    => $this->normalizer->normalize($get('phone')) ?: null,
            'phone_raw'         => $get('phone') ?: null,
            'address'           => $get('address') ?: null,
            'province'          => $get('province') ?: null,
            'city'              => $get('city') ?: null,
            'district'          => $get('district') ?: null,
            'subdistrict'       => $get('subdistrict') ?: null,
            'zip_code'          => $get('zip_code') ?: null,
            'cod_value'         => (float) str_replace(',', '.', $get('cod_value')),
            'product_value'     => (float) str_replace(',', '.', $get('product_value')),
            'goods_desc'        => $get('goods_desc') ?: null,
            'quantity'          => (int) ($get('quantity') ?: 0),
            'create_date'       => $this->toTimestamp($get('create_date')),
            'last_update'       => $this->toTimestamp($get('last_update')),
            'status_raw'        => $statusRaw ?: null,
            'status_internal'   => $statusInternal,
            'last_pod_status'   => $get('last_pod_status') ?: null,
            'shipping_fee'      => (float) str_replace(',', '.', $get('shipping_fee')),
            'shipping_discount' => (float) str_replace(',', '.', $get('shipping_discount')),
            'cod_fee'           => (float) str_replace(',', '.', $get('cod_fee')),
            'return_fee'        => (float) str_replace(',', '.', $get('return_fee')),
            'remark'            => $remark ?: null,
            'reference_code'    => $remark ?: null,
            'adv_resi_code'     => $adv,
            'cs_resi_code'      => $cs,
            'product_resi_code' => $prod,
            // Penanda validasi untuk importer.
            '_missing_key'      => $get('tracking_id') === '' && $get('platform_order_id') === '',
            '_unknown_status'   => $statusRaw !== '' && $statusInternal === null,
            '_remark_unmapped'  => $remark !== '' && str_contains($remark, 'XX'),
        ];
    }

    /**
     * Parse kode 14–16 char `AH` (§4.2/§7.1): DDMM + admin(2) + ADV(2) + CS(2) + Produk(2) + urut(4).
     * @return array{0:?string,1:?string,2:?string}
     */
    private function parseRemark(string $remark): array
    {
        $len = strlen($remark);
        if ($len < 12) {
            return [null, null, null];
        }

        return [
            substr($remark, 6, 2) ?: null,
            substr($remark, 8, 2) ?: null,
            substr($remark, 10, 2) ?: null,
        ];
    }

    /** @param array<string,string> $raw */
    private function normalizeKeys(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $k) ?? ''));
            $canon = self::ALIASES[$key] ?? $key;
            $out[$canon] = $v;
        }

        return $out;
    }

    private function toTimestamp(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
