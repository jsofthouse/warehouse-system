<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Kebijakan password default dipakai di semua tempat yang mengecek
        // Password::defaults() (ubah password, seeder, dst). Tanpa
        // ->uncompromised() supaya nggak bergantung ke API eksternal
        // (HaveIBeenPwned) — aplikasi ini internal, koneksi keluar nggak
        // dijamin selalu ada.
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers());
    }
}
