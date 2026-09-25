<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CsAgent;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderValidation;
use App\Models\Product;
use App\Services\PhoneClassificationService;
use App\Services\PhoneNormalizer;
use App\Services\ResiCodeBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly PhoneClassificationService $classifier,
        private readonly ResiCodeBuilder $resi,
    ) {
    }

    public function index(Request $request)
    {
        $q = Order::query()->with(['customer', 'csAgent', 'product', 'validations'])->latest('id');

        if ($search = $request->string('q')->toString()) {
            $norm = $this->normalizer->normalize($search);
            $q->where(function ($qq) use ($search, $norm) {
                $qq->where('reference_code', 'like', "%{$search}%")
                    ->orWhere('customer_phone_normalized', 'like', "%{$norm}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($cls = $request->string('classification')->toString()) {
            $q->where('classification', $cls);
        }
        if ($agg = $request->string('aggregator')->toString()) {
            $q->where('aggregator', $agg);
        }

        return Inertia::render('Orders/Index', [
            'orders' => $q->paginate(20)->withQueryString(),
            'filters' => [
                'q'              => $search ?? '',
                'classification' => $cls ?? '',
                'aggregator'     => $agg ?? '',
            ],
            'counts' => [
                'positif'         => Order::where('classification', 'positif')->count(),
                'negatif'         => Order::where('classification', 'negatif')->count(),
                'perlu_ditinjau'  => Order::where('classification', 'perlu_ditinjau')->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Orders/Create', [
            'csAgents' => CsAgent::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_date'      => ['required', 'date'],
            'cs_agent_id'     => ['nullable', 'exists:cs_agents,id'],
            'customer_name'   => ['required', 'string', 'max:120'],
            'phone'           => ['required', 'string', 'max:32'],
            'address_detail'  => ['required', 'string', 'max:255'],
            'kelurahan'       => ['nullable', 'string', 'max:120'],
            'kecamatan'       => ['nullable', 'string', 'max:120'],
            'kabupaten'       => ['nullable', 'string', 'max:120'],
            'kota'            => ['nullable', 'string', 'max:120'],
            'provinsi'        => ['nullable', 'string', 'max:120'],
            'zip_code'        => ['nullable', 'string', 'max:12'],
            'product_id'      => ['nullable', 'exists:products,id'],
            'product_detail'  => ['nullable', 'string', 'max:255'],
            'qty'             => ['required', 'integer', 'min:1'],
            'payment_method'  => ['required', 'in:COD,NON COD'],
            'price'           => ['required', 'numeric', 'min:0'],
            'weight'          => ['nullable', 'numeric', 'min:0'],
            'aggregator'      => ['required', 'in:mengantar,lincah'],
            'expedition'      => ['nullable', 'string', 'max:32'],
        ]);

        $normalized = $this->normalizer->normalize($data['phone']);

        $customer = Customer::firstOrCreate(
            ['phone_normalized' => $normalized],
            ['phone_raw' => $data['phone'], 'name' => $data['customer_name'], 'created_by' => $request->user()->id]
        );

        $cs    = ($data['cs_agent_id'] ?? null) ? CsAgent::find($data['cs_agent_id']) : null;
        $prod  = ($data['product_id'] ?? null) ? Product::find($data['product_id']) : null;
        $seq   = $this->resi->nextSequence($data['order_date']);
        $admin = $request->user()->admin_input_code ?? 1;

        $reference = $this->resi->build(
            $data['order_date'],
            $admin,
            $cs?->advertiser?->resi_code,
            $cs?->resi_cs_code,
            $prod?->resi_code,
            $seq
        );

        $order = Order::create([
            'reference_code'            => $reference,
            'order_date'                => $data['order_date'],
            'customer_id'               => $customer->id,
            'customer_phone_normalized' => $normalized,
            'cs_agent_id'               => $cs?->id,
            'product_id'                => $prod?->id,
            'address_detail'            => $data['address_detail'],
            'kelurahan'                 => $data['kelurahan'] ?? null,
            'kecamatan'                 => $data['kecamatan'] ?? null,
            'kabupaten'                 => $data['kabupaten'] ?? null,
            'kota'                      => $data['kota'] ?? null,
            'provinsi'                  => $data['provinsi'] ?? null,
            'zip_code'                  => $data['zip_code'] ?? null,
            'product_detail'            => $data['product_detail'] ?? $prod?->name,
            'qty'                       => $data['qty'],
            'payment_method'            => $data['payment_method'],
            'price'                     => $data['price'],
            'weight'                    => $data['weight'] ?? 0,
            'aggregator'                => $data['aggregator'],
            'expedition'                => $data['expedition'] ?? null,
            'admin_input_code'          => $admin,
            'adv_resi_code'             => $cs?->advertiser?->resi_code,
            'cs_resi_code'              => $cs?->resi_cs_code,
            'product_resi_code'         => $prod?->resi_code,
        ]);

        $result = $this->classifier->record($order, addressFilled: (bool) $data['address_detail']);

        return redirect()->route('orders.index')
            ->with('order_result', $result->toArray())
            ->with('flash', "Order {$order->reference_code} → " . strtoupper($result->classification));
    }

    /** Detail order + linimasa (prompt.md §7: ekspor, impor, perubahan status, koreksi, sumber nilai). */
    public function show(Order $order)
    {
        $order->load([
            'customer', 'csAgent.advertiser', 'product', 'ruleVersion', 'overrideBy',
            'validations.ruleVersion',
            'shipments.statusEvents',
            'exportBatchItems.exportBatch.user',
            'auditLogs.user',
        ]);

        return Inertia::render('Orders/Show', [
            'order'      => $order,
            'exportable' => $order->isExportable(),
            // Hitungan turunan (netShippingCost, urutan event) dihitung di server agar UI tinggal render.
            'shipments'  => $order->shipments->map(fn ($s) => [
                ...$s->toArray(),
                'net_shipping_cost' => $s->netShippingCost(),
                'status_events'     => $s->statusEvents->sortBy('status_date')->values()->toArray(),
            ])->values(),
        ]);
    }

    /** Koreksi klasifikasi manual yang tercatat audit (prompt.md §4.6 / §11). */
    public function override(Request $request, Order $order)
    {
        $data = $request->validate([
            'classification' => ['required', 'in:positif,negatif,perlu_ditinjau'],
            'reason'         => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $old = $order->classification;

        $order->update([
            'classification'     => $data['classification'],
            'is_manual_override' => true,
            'override_reason'    => $data['reason'],
            'override_by'        => $request->user()->id,
            'override_at'        => now(),
        ]);

        OrderValidation::create([
            'order_id'          => $order->id,
            'rule_version_id'   => $order->rule_version_id,
            'result'            => 'Koreksi manual → ' . strtoupper($data['classification']),
            'reason'            => $data['reason'],
            'evidence'          => ['manual' => true, 'old' => $old, 'new' => $data['classification']],
            'checked_at'        => now(),
        ]);

        AuditLog::record(
            'order.classification_override',
            $order,
            ['classification' => $old],
            ['classification' => $data['classification']],
            $data['reason']
        );

        return back()->with('flash', "Klasifikasi {$order->reference_code} diubah ke " . strtoupper($data['classification']) . '.');
    }
}
