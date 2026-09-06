<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Snapshot stok akhir hari, dipakai sebagai basis kg-hari Modul Invoice Sewa
// Gudang. Jam 23:55 (bukan 00:00) supaya jelas menghitung "hari ini" yang
// baru saja berakhir, tidak ambigu hari mana yang dimaksud — lihat
// "Modul Kartu Stok dan Stok Harian.md" §4.2 dan §9 No. 1.
Schedule::command('stok:hitung-harian')
    ->dailyAt('23:55')
    ->withoutOverlapping()
    ->onOneServer();
