<?php

namespace Tests\Feature;

use App\Models\CommissionEntry;
use App\Models\CommissionPayment;
use App\Models\CommissionPeriod;
use App\Models\CommissionRule;
use App\Models\CsAgent;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\CommissionService;
use Database\Seeders\CommissionRuleSeeder;
use Database\Seeders\ReferenceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Alur F (prompt.md §9; audit §9.1–§9.4): rantai OutputResi, tier komisi, periode CS 16–15
 * TERPISAH dari ADV bulan kalender, saldo berjalan (carry-over), dan pembekuan periode tertutup.
 */
class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceMasterSeeder::class);
        $this->seed(CommissionRuleSeeder::class);
    }

    private function calculator(): CommissionCalculator
    {
        return new CommissionCalculator();
    }

    private function service(): CommissionService
    {
        return app(CommissionService::class);
    }

    /** Resi COD contoh: produk PG (hpp 90.000 + packing 5.000, harga jual min 149.000 @1). */
    private function shipment(array $over = []): Shipment
    {
        $this->seq++;

        return Shipment::create(array_merge([
            'platform'          => 'mengantar',
            'tracking_id'       => 'T-' . $this->seq,
            'customer_phone'    => '628120000' . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'status_internal'   => 'diterima',
            'cod_value'         => 200000,
            'shipping_fee'      => 22000,
            'quantity'          => 1,
            'create_date'       => '2026-05-02 08:00:00',
            'adv_resi_code'     => 'A1',
            'cs_resi_code'      => 'C1',
            'product_resi_code' => 'PG',
        ], $over));
    }

    public function test_calculator_replicates_outputresi_chain_for_cod_diterima(): void
    {
        $calc = $this->calculator()->compute($this->shipment());

        $this->assertSame(0.0, $calc['p']);                    // COD → P = 0
        $this->assertSame(200000.0, $calc['q']);
        $this->assertSame(6000.0, $calc['t']);                 // 3% × 200.000
        $this->assertSame(660.0, $calc['u']);                  // 11% × 6.000
        $this->assertSame(28660.0, $calc['w']);                // R + T + U
        $this->assertSame(95000.0, $calc['ad_cogs_total']);    // 90.000 + 5.000 + 0
        $this->assertSame(76340.0, $calc['af_laba_tanpa_diskon']);
        $this->assertSame(5000.0, $calc['ag_komisi_order']);   // tier 26.000 ≤ AF < 89.000
        $this->assertSame(0.0, $calc['ah_komisi_transfer']);   // non-transfer
        $this->assertSame(5585.0, $calc['aj_komisi_ongkir']);  // ((Q) − W − harga jual min) × 25%
        $this->assertSame(10585.0, $calc['ak_komisi_total']);
        $this->assertSame(500.0, $calc['al_komisi_admin_input']);
        $this->assertSame(65255.0, $calc['am_laba_eksplisit']);
    }

    public function test_calculator_retur_penalty_and_zero_cod_fee(): void
    {
        $calc = $this->calculator()->compute($this->shipment([
            'status_internal' => 'retur',
            'cod_value'       => 150000,
        ]));

        $this->assertSame(0.0, $calc['t'], 'RETUR → biaya COD 0');
        $this->assertSame(0.0, $calc['u']);
        $this->assertSame(-27000.0, $calc['af_laba_tanpa_diskon']); // −(R + 0 + packing)
        $this->assertSame(-4400.0, $calc['ag_komisi_order'], 'penalti retur = −(0,2 × ongkir)');
        $this->assertSame(0.0, $calc['aj_komisi_ongkir'], 'RETUR → komisi ongkir 0');
        $this->assertSame(-4400.0, $calc['ak_komisi_total']);
        $this->assertSame(36900.0, $calc['am_laba_eksplisit']);
    }

    public function test_calculator_transfer_bonus_for_non_cod_diterima(): void
    {
        $calc = $this->calculator()->compute($this->shipment([
            'cod_value'         => 0,
            'product_value'     => 89000,
            'shipping_fee'      => 0,
            'product_resi_code' => 'BS', // hpp 45.000 + packing 3.000, harga jual min @1 = 79.000
        ]));

        $this->assertSame(89000.0, $calc['p']);
        $this->assertSame(0.0, $calc['q']);
        $this->assertSame(1000.0, $calc['ah_komisi_transfer'], 'P > 0 dan DITERIMA → bonus 1.000');
        $this->assertSame(5000.0, $calc['ag_komisi_order']);   // AF = 41.000
        $this->assertSame(2500.0, $calc['aj_komisi_ongkir']);  // ((P) − W − 79.000) × 25%
        $this->assertSame(8500.0, $calc['ak_komisi_total']);   // AG 5.000 + AH 1.000 + AJ 2.500
        $this->assertSame(32000.0, $calc['am_laba_eksplisit']);
    }

    public function test_tier_boundaries_including_source_gap(): void
    {
        $rule = CommissionRule::activeOn('order_tier', '2026-05-02')->first();
        $calc = $this->calculator();

        $this->assertSame(0.0, $calc->tierAmount(20000, $rule));       // < 26.000
        $this->assertSame(5000.0, $calc->tierAmount(50000, $rule));    // 26.000–88.999
        $this->assertSame(0.0, $calc->tierAmount(95000, $rule));       // gap 89.000–99.001 (U5)
        $this->assertSame(9900.1, $calc->tierAmount(99001, $rule));    // ≥ 99.001 → 10%
        $this->assertSame(15000.0, $calc->tierAmount(150000, $rule));
    }

    public function test_new_rule_version_does_not_change_old_shipment(): void
    {
        $old = $this->shipment(); // 2026-05-02

        CommissionRule::create([
            'key' => 'order_tier', 'applies_to' => 'cs', 'name' => 'Tier revisi Juni',
            'params' => ['brackets' => [
                ['lt' => 26000, 'amount' => 0],
                ['lt' => 89000, 'amount' => 11111],
                ['gap_to' => 99001, 'amount' => 0],
                ['gte' => 99001, 'percent' => 10],
            ]],
            'effective_from' => '2026-06-01', 'is_active' => true,
        ]);

        // Resi lama tetap memakai tarif pada TANGGAL RESI (audit §9.1).
        $calcOld = $this->calculator()->compute($old);
        $this->assertSame(5000.0, $calcOld['ag_komisi_order']);

        // Resi baru (Juni) memakai versi baru.
        $new = $this->shipment(['create_date' => '2026-06-02 08:00:00']);
        $calcNew = $this->calculator()->compute($new);
        $this->assertSame(11111.0, $calcNew['ag_komisi_order']);
    }

    public function test_cs_window_is_16_to_15_and_adv_window_is_calendar_month(): void
    {
        [$csStart, $csEnd] = CommissionPeriod::windowFor('cs', 2026, 5);
        $this->assertSame('2026-04-16', $csStart->toDateString());
        $this->assertSame('2026-05-15', $csEnd->toDateString());

        [$advStart, $advEnd] = CommissionPeriod::windowFor('adv', 2026, 5);
        $this->assertSame('2026-05-01', $advStart->toDateString());
        $this->assertSame('2026-05-31', $advEnd->toDateString());

        $cs = CsAgent::where('lookup_key', 'GL01')->first();
        $period = $this->service()->periodFor('cs', $cs->id, 2026, 5);
        $this->assertSame(CommissionPeriod::CS_PERIOD_TYPE, $period->period_type);
        $this->assertStringContainsString('CS 16–15', $period->label);
        $this->assertStringContainsString('16/04/2026', $period->label);
    }

    public function test_compute_creates_entries_and_uses_cs_window(): void
    {
        $cs = CsAgent::where('lookup_key', 'GL01')->first();

        $s1 = $this->shipment();                                                     // 02/05 diterima
        $s2 = $this->shipment(['create_date' => '2026-05-03 08:00:00', 'status_internal' => 'dikirim']);
        $s3 = $this->shipment(['create_date' => '2026-04-20 08:00:00']);             // masih dalam 16 Apr–15 Mei
        $this->shipment(['create_date' => '2026-04-10 08:00:00']);                   // di luar jendela
        $this->shipment(['create_date' => '2026-05-04 08:00:00', 'cs_resi_code' => 'C2']); // CS lain

        $period = $this->service()->periodFor('cs', $cs->id, 2026, 5);
        $this->service()->compute($period);
        $stats = $this->service()->stats($period);

        $this->assertSame(3, $stats['qty'], 'hanya resi CS GL01 (Ani) dalam jendela 16/04–15/05');
        $this->assertSame(15, $stats['entries'], '5 komponen × 3 resi');
        $this->assertSame(2, (int) $stats['counts']['diterima'], 's1 (02/05) + s3 (20/04)');
        $this->assertSame(1, (int) $stats['counts']['dikirim']);
        $this->assertSame(66.67, $stats['pct_close']);

        // s1 fully payable, s2 on progress — komponen order s1 = 5.000.
        $orderEntry = CommissionEntry::where('shipment_id', $s1->id)->where('component', 'order')->first();
        $this->assertSame(5000.0, (float) $orderEntry->amount);
        $this->assertTrue($orderEntry->is_payable);
        $this->assertFalse(CommissionEntry::where('shipment_id', $s2->id)->first()->is_payable);

        $expectedPayable = 10585.0 + 10585.0; // s1 + s3 (identik), s2 on-progress
        $this->assertSame($expectedPayable, $stats['payable']);
        $this->assertSame(10585.0 * 3 - $expectedPayable, $stats['on_progress'], 'selisih payable adalah porsi s2');
    }

    public function test_close_moves_unpaid_balance_to_next_period_and_freezes(): void
    {
        $cs = CsAgent::where('lookup_key', 'GL01')->first();
        $user = User::create([
            'name' => 'Finance', 'email' => 'finance@arj.test',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        $this->shipment(); // diterima → payable 10.585
        $period = $this->service()->periodFor('cs', $cs->id, 2026, 5);
        $this->service()->compute($period);

        CommissionPayment::create([
            'commission_period_id' => $period->id, 'owner_type' => 'cs', 'owner_id' => $cs->id,
            'paid_date' => '2026-05-20', 'amount' => 1000, 'approved_by' => $user->id,
        ]);

        $stats = $this->service()->stats($period);
        $this->assertSame(1000.0, $stats['paid']);
        $unpaid = $stats['unpaid'];
        $this->assertSame(9585.0, $unpaid);

        $this->service()->close($period, $user);
        $period->refresh();
        $this->assertTrue($period->isClosed());
        $this->assertSame($user->id, $period->closed_by);

        // Sisa dipindahkan ke periode berikutnya (CS Juni = 16 Mei–15 Juni).
        $next = CommissionPeriod::where('owner_type', 'cs')->where('owner_id', $cs->id)
            ->whereDate('start_date', '2026-05-16')->first();
        $this->assertNotNull($next);
        $this->assertSame($unpaid, (float) $next->carried_balance);
        $this->assertStringContainsString('16/05/2026', $next->label);

        // Periode tertutup tidak boleh dihitung ulang (audit §9.1).
        try {
            $this->service()->compute($period);
            $this->fail('compute() pada periode tertutup harus gagal');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        // Isi entries tetap beku.
        $this->assertSame($stats['entries'], (int) $period->entries()->count());
    }
}
