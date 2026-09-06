<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa logout user yang akunnya dinonaktifkan (is_active = false) di tengah
 * sesi aktif — misal super admin menonaktifkan user lain saat user itu masih
 * login. Dicek per-request, bukan cuma saat login (lihat LoginRequest).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi Super Admin.']);
        }

        return $next($request);
    }
}
