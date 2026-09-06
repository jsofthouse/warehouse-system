<?php

namespace App\Events;

use App\Models\SuratJalan;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipicu setelah pembatalan surat jalan berhasil (mutasi balik sudah tertulis).
 *
 * Belum ada listener-nya sekarang. Ini titik gantung buat modul Invoice:
 * pembatalan menulis mutasi IN bertanggal dokumen aslinya, jadi `stok_harian`
 * dari tanggal itu ke depan wajib dihitung ulang ("10-modul-surat-jalan.md"
 * §4.6.6 — pola sama dengan PenerimaanDibatalkan).
 */
class SuratJalanDibatalkan
{
    use Dispatchable;

    public function __construct(public SuratJalan $suratJalan) {}
}
