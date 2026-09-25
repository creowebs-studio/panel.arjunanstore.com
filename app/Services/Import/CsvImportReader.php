<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Pembaca file impor hasil platform menjadi daftar baris asosiatif (header => nilai).
 *
 * Format acuan: export Mengantar/Lincah (CSV/TSV bertanda kutip, pemisah `;` atau `,`).
 * Deteksi otomatis BOM, pemisah, dan header (baris pertama). Nomor/ID tetap STRING
 * (prompt.md §7 — jangan ubah digit panjang / nol depan).
 */
class CsvImportReader
{
    /**
     * @return array<int, array<string,string>> baris (kolom => nilai mentah)
     */
    public function read(UploadedFile $file): array
    {
        $abs = $file->getRealPath();
        if ($abs === false) {
            throw new RuntimeException('File tidak terbaca.');
        }
        $content = file_get_contents($abs);
        // Buang BOM UTF-8 bila ada.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split("/\r\n|\n|\r/", trim((string) $content));
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if (count($lines) < 1) {
            return [];
        }

        $delim = $this->detectDelimiter($lines[0]);
        $header = $this->parseLine($lines[0], $delim);
        $header = array_map(fn ($h) => trim($h), $header);

        $rows = [];
        for ($i = 1, $n = count($lines); $i < $n; $i++) {
            $cells = $this->parseLine($lines[$i], $delim);
            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = $cells[$idx] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** Parse satu baris CSV dengan dukungan tanda kutip ganda. */
    private function parseLine(string $line, string $delim): array
    {
        $cur = @str_getcsv($line, $delim, '"', '\\');

        return is_array($cur) ? $cur : [];
    }
}
