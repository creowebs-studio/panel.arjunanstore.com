<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Jembatan Inertia: root view + props bersama (auth user, peran, izin, flash).
 * UI React/Ant Design membaca props ini untuk menu & pengecekan akses klien;
 * penegakan akses tetap 100% di backend (middleware role/permission).
 */
class HandleInertiaRequests extends Middleware
{
    /** Blade root yang membungkus halaman React (resources/views/app.blade.php). */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn () => $request->user()
                ? [
                    'user' => [
                        'id'          => $request->user()->id,
                        'name'        => $request->user()->name,
                        'email'       => $request->user()->email,
                        'roles'       => $request->user()->roles->pluck('slug')->all(),
                        'permissions' => $request->user()->permissionSlugs(),
                    ],
                ]
                : null,
            'flash' => fn () => [
                'success' => $request->session()->get('flash') ?? $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            // Hasil klasifikasi order terakhir (OrderController::store) — kartu info di halaman Daftar Order.
            'orderResult' => fn () => $request->session()->get('order_result'),
        ]);
    }
}
