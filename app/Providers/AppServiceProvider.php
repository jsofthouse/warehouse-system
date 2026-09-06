<?php

namespace App\Providers;

use App\Models\Gudang;
use App\Models\Item;
use App\Models\Lokasi;
use App\Models\Penerimaan;
use App\Models\SuratJalan;
use App\Models\TarifSewa;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
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

        // Tampilan paginasi bawaan Laravel itu Tailwind, bentrok sama tema
        // Bootstrap 5/Tabler yang dipakai di seluruh halaman (lihat CLAUDE.md
        // §3 — Tailwind sengaja tidak dipakai). View kustom ini pakai markup
        // Bootstrap 5 asli (page-item/page-link, didukung Tabler tanpa CSS
        // tambahan) plus baris info "Menampilkan X-Y dari Z data" ala
        // DataTables, tapi tanpa perlu jQuery/DataTables (lihat
        // docs/07-panduan-frontend.md §2). Berlaku otomatis untuk semua
        // pemanggilan ->links() di seluruh aplikasi.
        // Belum ada layar yang pakai simplePaginate() (cuma paginate() biasa),
        // jadi defaultSimpleView() sengaja tidak disentuh — view kustom di
        // atas mengandalkan $elements dan total(), yang tidak tersedia di
        // paginator versi simple.
        Paginator::defaultView('partials.pagination');

        // Alias pendek untuk subjek_type polimorfik di activity_log (lihat
        // docs/12-modul-activity-log.md §3.2). Tanpa ini, subjek_type
        // menyimpan nama kelas penuh (App\Models\Gudang, dst) yang lebih
        // ribet dipetakan ke label Indonesia di dropdown filter "Jenis
        // Dokumen", dan berubah kalau kelasnya di-refactor pindah namespace.
        // Aman diterapkan sekarang meski sudah ada baris activity_log lama
        // berisi nama kelas penuh — morph map Eloquent tetap lenient
        // terhadap nilai yang tidak ada di map.
        Relation::morphMap([
            'gudang' => Gudang::class,
            'item' => Item::class,
            'lokasi' => Lokasi::class,
            'tarif_sewa' => TarifSewa::class,
            'user' => User::class,
            'penerimaan' => Penerimaan::class,
            'surat_jalan' => SuratJalan::class,
        ]);
    }
}
