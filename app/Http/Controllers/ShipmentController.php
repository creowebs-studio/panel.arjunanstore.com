<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\PhoneNormalizer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Alur D — Master Resi (menggantikan DBMengantar + OutputResi). Daftar + detail berlinimasa
 * (prompt.md §7). Pencarian: telepon, Order ID platform, resi/tracking.
 */
class ShipmentController extends Controller
{
    public function __construct(private readonly PhoneNormalizer $normalizer)
    {
    }

    public function index(Request $request)
    {
        $q = Shipment::query()->with('order')->latest('id');

        if ($search = $request->string('q')->toString()) {
            $norm = $this->normalizer->normalize($search);
            $q->where(function ($qq) use ($search, $norm) {
                $qq->where('tracking_id', 'like', "%{$search}%")
                    ->orWhere('platform_order_id', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$norm}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status_internal', $status);
        }
        if ($platform = $request->string('platform')->toString()) {
            $q->where('platform', $platform);
        }

        return Inertia::render('Shipments/Index', [
            'shipments' => $q->paginate(25)->withQueryString(),
            'filters'   => [
                'q'        => $search ?? '',
                'status'   => $status ?? '',
                'platform' => $platform ?? '',
            ],
        ]);
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['order.customer', 'statusEvents' => fn ($e) => $e->orderBy('status_date')]);

        return Inertia::render('Shipments/Show', [
            'shipment'     => $shipment,
            'events'       => $shipment->statusEvents->sortBy('status_date'),
            'net_shipping' => $shipment->netShippingCost(),
        ]);
    }
}
