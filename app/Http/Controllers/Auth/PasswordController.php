<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    /**
     * Update password milik user yang sedang login. `current_password` divalidasi
     * lewat rule bawaan Laravel (dicocokkan ke guard 'web'), password baru wajib
     * lolos Password::defaults() (lihat konfigurasi di AppServiceProvider).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        // Putuskan sesi lain milik user ini (device/browser lain) — dipaksa
        // login ulang pakai password baru. SESSION_DRIVER=database, jadi bisa
        // langsung hapus baris sesi lain di tabel `sessions`.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'aksi' => 'ubah_password',
            'deskripsi' => 'User mengubah password sendiri',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Password berhasil diubah.');
    }
}
