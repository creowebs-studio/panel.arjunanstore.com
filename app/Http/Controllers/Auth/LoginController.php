<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Autentikasi minimal (tanpa scaffolding npm) — prompt.md §3 "sediakan login".
 */
class LoginController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('orders.index');
        }

        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('orders.index'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
