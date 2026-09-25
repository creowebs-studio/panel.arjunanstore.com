<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\PhoneClassificationService;
use App\Services\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji aturan positif/negatif PERSIS §4.3 dan normalisasi §4.2 (prompt.md kriteria #1 & #9).
 */
class PhoneClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function classify(string $phone): string
    {
        return (new PhoneClassificationService())->evaluate($phone, true)->classification;
    }

    private function ship(string $phone, string $status, string $day, string $resi): void
    {
        Shipment::create([
            'platform'        => 'mengantar',
            'tracking_id'     => $resi,
            'customer_phone'  => $phone,
            'status_internal' => $status,
            'create_date'     => $day . ' 10:00:00',
        ]);
    }

    public function test_normalization_variants(): void
    {
        $n = new PhoneNormalizer();
        $this->assertSame('6281234567890', $n->normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', $n->normalize('6281234567890'));
        $this->assertSame('6281234567890', $n->normalize('81234567890'));   // tanpa 0/62 → +62
        $this->assertSame('6281234567890', $n->normalize(' 0812 3456 7890 '));
    }

    public function test_no_history_is_positif(): void
    {
        $this->assertSame('positif', $this->classify('628111000000'));
    }

    public function test_previously_delivered_is_negatif(): void
    {
        $this->ship('628111000001', 'diterima', '2026-05-01', 'RESI-A1');
        $this->assertSame('negatif', $this->classify('628111000001'));
    }

    public function test_in_progress_is_negatif(): void
    {
        $this->ship('628111000002', 'dikirim', '2026-05-02', 'RESI-A2');
        $this->assertSame('negatif', $this->classify('628111000002'));
    }

    public function test_last_retur_is_negatif(): void
    {
        $this->ship('628111000003', 'diterima', '2026-05-03', 'RESI-A3');
        $this->ship('628111000003', 'retur', '2026-05-09', 'RESI-A3b');
        $this->assertSame('negatif', $this->classify('628111000003'));
    }

    public function test_only_undel_history_still_positif(): void
    {
        $this->ship('628111000004', 'undel', '2026-05-04', 'RESI-A4');
        $this->assertSame('positif', $this->classify('628111000004'));
    }

    public function test_incomplete_data_needs_review(): void
    {
        $res = (new PhoneClassificationService())->evaluate('628111000005', false);
        $this->assertSame('perlu_ditinjau', $res->classification);
        $this->assertSame('', $res->final);
    }
}
