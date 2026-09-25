<?php

namespace App\Services\Import;

/**
 * Memetakan SATU baris laporan kampanye (export Meta Ads — tab `Rekap`, audit STAGE1 §8.1)
 * menjadi field kanonik untuk `campaigns` + `marketing_daily_reports`.
 * Murni & mudah diuji; angka diformat ulang dari format Indonesia (Rp, titik ribuan).
 */
class MarketingRowMapper
{
    /** Alias header (huruf kecil, spasi tunggal) → kunci kanonik (kolom A..V `Rekap`). */
    private const ALIASES = [
        'awal pelaporan' => 'date_start', 'reporting starts' => 'date_start', 'reporting start' => 'date_start',
        'akhir pelaporan' => 'date_end', 'reporting ends' => 'date_end', 'reporting end' => 'date_end',
        'nama kampanye' => 'campaign_name', 'campaign name' => 'campaign_name', 'kampanye' => 'campaign_name',
        'penayangan' => 'delivery_status', 'delivery' => 'delivery_status',
        // Header tab `Rekap` workbook apa adanya (Tahap 6 — migrasi):
        'penayangan kampanye' => 'delivery_status',
        'atribusi' => 'attribution', 'attribution setting' => 'attribution',
        'pengaturan atribusi' => 'attribution',
        'hasil' => 'results', 'results' => 'results',
        'indikator hasil' => 'result_indicator', 'result indicator' => 'result_indicator',
        'jangkauan' => 'reach', 'reach' => 'reach',
        'frekuensi' => 'frequency', 'frequency' => 'frequency',
        'biaya/hasil' => 'cost_per_result', 'biaya hasil' => 'cost_per_result', 'cost per result' => 'cost_per_result',
        'biaya per hasil' => 'cost_per_result',
        'anggaran set iklan' => 'budget_set', 'ad set budget' => 'budget_set',
        'jenis' => 'budget_type', 'budget type' => 'budget_type',
        'jenis anggaran set iklan' => 'budget_type',
        'jumlah dibelanjakan (idr)' => 'spend_raw', 'jumlah dibelanjakan' => 'spend_raw',
        'jumlah yang dibelanjakan (idr)' => 'spend_raw', 'jumlah yang dibelanjakan' => 'spend_raw',
        'amount spent (idr)' => 'spend_raw', 'amount spent' => 'spend_raw', 'spend' => 'spend_raw',
        'berakhir' => 'ended_at', 'ends' => 'ended_at',
        'impressi' => 'impressions', 'impressions' => 'impressions', 'impresi' => 'impressions',
        'cpm' => 'cpm',
        'cpm (biaya per 1.000 tayangan) (idr)' => 'cpm',
        'klik tautan' => 'clicks_link', 'link clicks' => 'clicks_link', 'klik tautan unik' => 'clicks_link',
        'cpc' => 'cpc_link',
        'cpc (biaya per klik tautan) (idr)' => 'cpc_link',
        'ctr' => 'ctr_link',
        'ctr (rasio klik tayang tautan)' => 'ctr_link',
        'klik semua' => 'clicks_all', 'clicks (all)' => 'clicks_all', 'klik (semua)' => 'clicks_all',
        'ctr semua' => 'ctr_all', 'ctr (all)' => 'ctr_all', 'ctr (semua)' => 'ctr_all',
        'cpc semua' => 'cpc_all', 'cpc (all)' => 'cpc_all', 'cpc (semua)' => 'cpc_all',
        'cpc (semua) (idr)' => 'cpc_all',
    ];

    /**
     * @param array<string,string> $raw
     * @return array<string,mixed>
     */
    public function map(array $raw): array
    {
        $r = $this->normalizeKeys($raw);
        $get = fn (string $k) => trim((string) ($r[$k] ?? ''));

        $name = $get('campaign_name');
        $start = $this->toDate($get('date_start'));
        $end = $this->toDate($get('date_end'));

        // Kolom DASAR — sumber kebenaran untuk memverifikasi kolom turunan
        // (semua kolom turunan = fungsi eksak dari kolom-kolom ini).
        $spend       = $this->toNumber($get('spend_raw'));
        $impressions = $this->toNumber($get('impressions'));
        $reach       = $this->toNumber($get('reach'));
        $results     = $this->toNumber($get('results'));
        $clicksLink  = $this->toNumber($get('clicks_link'));
        $clicksAll   = $this->toNumber($get('clicks_all'));

        // Kolom turunan workbook sebagian rusak (titik desimal termakan locale id-ID:
        // '26225,64891' tersimpan '2622564891') atau kosong — dihitung ulang secara
        // deterministik dari kolom dasar (audit Tahap 6); sel menyimpang dicatat.
        $normalized = [];
        $frequency     = $this->metric('Frekuensi', $reach > 0 ? $impressions / $reach : null, $get('frequency'), $normalized);
        $costPerResult = $this->metric('Biaya per Hasil', $results > 0 ? $spend / $results : null, $get('cost_per_result'), $normalized);
        $cpm           = $this->metric('CPM', $impressions > 0 ? $spend / $impressions * 1000 : null, $get('cpm'), $normalized);
        $cpcLink       = $this->metric('CPC', $clicksLink > 0 ? $spend / $clicksLink : null, $get('cpc_link'), $normalized);
        $ctrLink       = $this->metric('CTR', $impressions > 0 ? $clicksLink / $impressions * 100 : null, str_replace('%', '', $get('ctr_link')), $normalized, 4);
        $cpcAll        = $this->metric('CPC (Semua)', $clicksAll > 0 ? $spend / $clicksAll : null, $get('cpc_all'), $normalized);
        $ctrAll        = $this->metric('CTR (Semua)', $impressions > 0 ? $clicksAll / $impressions * 100 : null, str_replace('%', '', $get('ctr_all')), $normalized, 4);

        return [
            'campaign_name'    => $name ?: null,
            'date_start'       => $start,
            'date_end'         => $end,
            'periode'          => $start ? date('ym', strtotime($start)) : null, // TEXT(tgl,"YYMM")
            'delivery_status'  => $get('delivery_status') ?: null,
            'attribution'      => $get('attribution') ?: null,
            'results'          => (int) $results,
            'result_indicator' => $get('result_indicator') ?: null,
            'reach'            => (int) $reach,
            'frequency'        => $frequency,
            'cost_per_result'  => $costPerResult,
            'budget_set'       => round($this->toNumber($get('budget_set')), 2),
            'budget_type'      => $get('budget_type') ?: null,
            'spend_raw'        => round($spend, 2),
            'ended_at'         => $this->toDate($get('ended_at')),
            'impressions'      => (int) $impressions,
            'cpm'              => $cpm,
            'clicks_link'      => (int) $clicksLink,
            'cpc_link'         => $cpcLink,
            'ctr_link'         => $ctrLink,
            'clicks_all'       => (int) $clicksAll,
            'ctr_all'          => $ctrAll,
            'cpc_all'          => $cpcAll,
            // Penanda validasi untuk importer.
            '_missing_key'       => $name === '' || $start === null || $end === null,
            '_metric_normalized' => $normalized,
        ];
    }

    /**
     * Bandingkan sel turunan workbook terhadap hitungan dari kolom dasar.
     * Sel kosong atau menyimpang (toleransi 0,1% untuk pembulatan) → pakai hasil
     * hitungan deterministik dan catat labelnya; kolom dasar nol → tak dapat
     * diverifikasi, nilai tersimpan dipertahankan apa adanya.
     */
    private function metric(string $label, ?float $expected, string $raw, array &$normalized, int $precision = 2): float
    {
        $stored = $this->toNumber($raw);
        if ($expected === null) {
            return round($stored, $precision);
        }

        $expected = round($expected, $precision);
        if ($raw === '' || abs($stored - $expected) > max(0.01, abs($expected) * 0.001)) {
            $normalized[] = $raw === '' ? $label . ' (kosong)' : $label;

            return $expected;
        }

        return round($stored, $precision);
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

    /** "Rp 1.234.567,5" / "1,234,567.50" / "150000" → 1234567.5 */
    private function toNumber(string $value): float
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return 0.0;
        }
        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';

        if (str_contains($value, ',') && str_contains($value, '.')) {
            // Kedua pemisah ada → '.' ribuan, ',' desimal (format ID).
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value); // 1.234.567 = ribuan
        }

        return (float) $value;
    }

    private function toDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $value);
            if ($dt !== false && $dt->format($fmt) === $value) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
