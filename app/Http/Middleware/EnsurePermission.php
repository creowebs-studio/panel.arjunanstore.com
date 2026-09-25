<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang berbasis PERMISSION (lebih granular dari role). Alias 'permission'.
 * Pemakaian: ->middleware('permission:order.export') — semua slug yang disebut harus dimiliki.
 * Superadmin selalu lolos.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                abort(403, "Izin '{$permission}' diperlukan untuk aksi ini.");
            }
        }

        return $next($request);
    }
}
