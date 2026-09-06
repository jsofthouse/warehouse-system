<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Siapa saja boleh mencoba login — pengecekan kredensial ada di authenticate().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Coba autentikasi request. `is_active` disertakan langsung di kondisi query
     * supaya user nonaktif ditolak di level yang sama dengan email/password salah
     * (nggak ada perbedaan pesan yang bisa dipakai buat menebak akun mana yang
     * dinonaktifkan).
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(
            [
                ...$this->only('email', 'password'),
                'is_active' => true,
            ],
            $this->boolean('remember')
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Pastikan request belum kena rate limit — max 5x percobaan gagal per
     * kombinasi email+IP, lock 1 menit (lihat spesifikasi §7: rate limiting login).
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Kunci rate limiter: email (dinormalisasi) + IP, supaya penyerang nggak bisa
     * lock akun orang lain cuma dengan spam email korban dari banyak IP, dan
     * nggak bisa brute-force satu IP lintas banyak email tanpa kena limit.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
