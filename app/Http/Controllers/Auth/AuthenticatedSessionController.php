<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Tampilkan form login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Proses login. Validasi kredensial + rate limiting ada di LoginRequest.
     * Pencatatan ke activity_log ditangani listener CatatLoginBerhasil/
     * CatatLoginGagal lewat event Auth::attempt() bawaan Laravel — lihat
     * docs/12-modul-activity-log.md §4.1.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Regenerate session ID supaya nggak kena session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Logout, invalidate session, dan regenerate token CSRF. Logout sengaja
     * tidak dicatat ke activity_log (docs/12-modul-activity-log.md §2,
     * keputusan #2).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
