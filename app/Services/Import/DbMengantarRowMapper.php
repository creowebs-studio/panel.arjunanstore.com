<?php

namespace App\Services\Import;

use App\Models\CarrierStatusMapping;
use App\Services\PhoneNormalizer;

/**
 * Pemetaan SATU baris `DBMengantar` (audit STAGE1 §7.1) untuk MIGRASI Tahap 6.
 *
 * Berbeda dari `MengantarRowMapper` (format paste export berhuruf), remark internal
 * workbook berbentuk 15 digit: `[DDMM][admin][kode CS 3 digit][kode resi produk 2 digit][urut 4]`,
 * dan kode ADV/CS/Produk di-resolve PERSIS rumus workbook:
 *   - `MID(AB,7,3)` → ADV_CS kolom I → {kode ADV (kolom E), kode CS (kolom B)}
 *   - `MID(AB,10,2)` → Produk kolom D → kode produk (kolom C)
 * Hasil disimpan pada kolom resi shipment agar cocok dengan `RekapAdvService::codeDimensions`
 * (advertiser.code, csAgent.lookup_key, product.resi_code).
 */
class DbMengantarRowMapper
{
    /**
     * @param array<string,array{adv:string,cs:string}> $advCsMap   token kode CS → {adv, cs}
     * @param array<string,string>                     $productMap token kode resi produk → kode produk
     */
    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly array $advCsMap = [],
        private readonly array $productMap = [],
    ) {
    }

    /**
     * @param array<string,string> $raw baris CSV ber-kunci huruf kolom (A..AL)
     * @return array<string,mixed>
     */
    public function map(array $raw, string $platform = 'mengantar'): array
    {
        $get = fn (string $k) => trim((string) ($raw[$k] ?? ''));

        $remark = $get('X');
        $ab = $this->correctedRemark($remark, $get('AB'));
        [$adv, $cs, $prod] = $this->resolveCodes($ab);
        $resolved = $adv !== null && $cs !== null && $prod !== null;

        $statusRaw = $get('R');
        $statusInternal = CarrierStatusMapping::internalFor($platform, $statusRaw !== '' ? $statusRaw : null);

        return [
            'platform'          => $platform,
            'expedition'        => $get('A') ?: null,
            'platform_order_id' => $get('B') ?: null,
            'tracking_id'       => $get('C') ?: null,
            'return_resi'       => $get('D') ?: null,
            'customer_name'     => $get('E') ?: null,
            'customer_phone'    => $this->normalizer->normalize($get('F')) ?: null,
            'phone_raw'         => $get('F') ?: null,
            'address'           => $get('G') ?: null,
            'province'          => $get('H') ?: null,
            'city'              => $get('I') ?: null,
            'district'          => $get('J') ?: null,
            'subdistrict'       => $get('Y') ?: null,
            'zip_code'          => $get('K') ?: null,
            'cod_value'         => (float) $get('L'),
            'product_value'     => (float) $get('M'),
            'goods_desc'        => $get('N') ?: null,
            'quantity'          => (int) ((float) $get('O')),
            'create_date'       => $this->toTimestamp($get('P')),
            'last_update'       => $this->toTimestamp($get('Q')),
            'status_raw'        => $statusRaw ?: null,
            'status_internal'   => $statusInternal,
            'last_pod_status'   => $get('S') ?: null,
            'shipping_fee'      => (float) $get('T'),
            'shipping_discount' => (float) $get('U'),
            'cod_fee'           => (float) $get('V'),
            'return_fee'        => (float) $get('W'),
            'remark'            => $remark ?: null,
            'remark_corrected'  => $ab ?: null,
            'reference_code'    => $remark ?: null,
            'adv_resi_code'     => $adv,
            'cs_resi_code'      => $cs,
            'product_resi_code' => $prod,
            'campaign_name'     => $resolved ? $adv . '-' . $cs . '-' . $prod : null,
            // Penanda validasi untuk importer (Data Error, bukan disembunyikan).
            '_missing_key'      => $get('C') === '' && $get('B') === '',
            '_unknown_status'   => $statusRaw !== '' && $statusInternal === null,
            '_remark_unmapped'  => $remark !== '' && ! $resolved,
        ];
    }

    /** Kolom AB workbook: X 14 digit → beri awalan "0" (audit §7.1); pakai kolom AB bila tersedia. */
    private function correctedRemark(string $remark, string $ab): string
    {
        if ($ab !== '') {
            return strlen($ab) === 14 ? '0' . $ab : $ab;
        }
        if ($remark === '') {
            return '';
        }

        return strlen($remark) === 14 ? '0' . $remark : $remark;
    }

    /**
     * Token internal numerik: `MID(AB,7,3)` = kode CS (ADV_CS!I), `MID(AB,10,2)` = kode resi produk (Produk!D).
     * Fallback format lama berhuruf: ADV `substr(6,2)`, CS `substr(8,2)`, produk `substr(10,2)`.
     *
     * @return array{0:?string,1:?string,2:?string}
     */
    private function resolveCodes(string $ab): array
    {
        if ($ab === '' || strlen($ab) < 12) {
            return [null, null, null];
        }

        if (ctype_digit($ab)) {
            $csToken = substr($ab, 6, 3);
            $prodToken = self::normalizeToken(substr($ab, 9, 2));
            $entry = $this->advCsMap[$csToken] ?? null;

            return [
                $entry['adv'] ?? null,
                $entry['cs'] ?? null,
                $this->productMap[$prodToken] ?? null,
            ];
        }

        return [substr($ab, 6, 2) ?: null, substr($ab, 8, 2) ?: null, substr($ab, 10, 2) ?: null];
    }

    /** Token numerik "11"/"11.0"/" 09" → "11"/"11"/"9" agar cocok dengan peta workbook. */
    public static function normalizeToken(string $token): string
    {
        $t = trim($token);
        if (preg_match('/^\d+(\.0+)?$/', $t)) {
            return (string) (int) $t;
        }

        return $t;
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
