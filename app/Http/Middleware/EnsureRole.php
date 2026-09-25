<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang berbasis ROLE (prompt.md §4). Alias terdaftar di bootstrap/app.php sebagai 'role'.
 * Pemakaian: ->middleware('role:admin_order,admin_pengiriman') — cukup salah satu cocok.
 * Superadmin selalu lolos.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        if ($user->isSuperAdmin() || $user->hasRole(...$roles)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses untuk bagian ini.');
    }
}
