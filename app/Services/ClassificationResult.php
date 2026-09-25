<?php

namespace App\Services;

/**
 * Hasil evaluasi klasifikasi nomor (positif/negatif/perlu ditinjau) beserta
 * jejak perantara AK/AP yang menjustifikasinya (audit STAGE1 §4.3).
 */
class ClassificationResult
{
    public function __construct(
        public readonly string $classification,          // positif|negatif|perlu_ditinjau
        public readonly string $final,                   // "Output Data Positif"/"Output Data Negatif"/""
        public readonly ?string $byWa,                   // AP: Negatif/Positif/Data Belum Tersedia
        public readonly ?string $lastOrderVerdict,       // AK: Negatif/Positif
        public readonly ?string $inProgressResi,         // AM
        public readonly int $returCount,                 // AN
        public readonly int $terimaCount,                // AO
        public readonly string $reason,
        public readonly array $evidence = [],
    ) {
    }

    public function isPositif(): bool
    {
        return $this->classification === 'positif';
    }

    public function toArray(): array
    {
        return [
            'classification'     => $this->classification,
            'final'              => $this->final,
            'by_wa'              => $this->byWa,
            'last_order_verdict' => $this->lastOrderVerdict,
            'in_progress_resi'   => $this->inProgressResi,
            'retur_count'        => $this->returCount,
            'terima_count'       => $this->terimaCount,
            'reason'             => $this->reason,
            'evidence'           => $this->evidence,
        ];
    }
}
