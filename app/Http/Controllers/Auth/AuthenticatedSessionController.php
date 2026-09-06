<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
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
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Regenerate session ID supaya nggak kena session fixation.
        $request->session()->regenerate();

        $this->catat($request, 'login', 'User login: '.$request->user()->email);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Logout, invalidate session, dan regenerate token CSRF.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $this->catat($request, 'logout', 'User logout: '.$user->email, $user->id);
        }

        return redirect()->route('login');
    }

    /**
     * Catat aksi login/logout ke activity log (ActivityLog — lihat spesifikasi
     * §7: "Log aktivitas untuk semua posting dan pembatalan", diperluas ke
     * login/logout supaya jejak akses juga bisa diaudit).
     */
    private function catat(Request $request, string $aksi, string $deskripsi, ?int $userId = null): void
    {
        ActivityLog::create([
            'user_id' => $userId ?? $request->user()?->id,
            'aksi' => $aksi,
            'subjek_type' => null,
            'subjek_id' => null,
            'deskripsi' => $deskripsi,
            'ip_address' => $request->ip(),
        ]);
    }
}
