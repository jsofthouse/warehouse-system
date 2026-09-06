<?php

namespace App\Events;

use App\Models\Penerimaan;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipicu setelah pembatalan penerimaan berhasil (mutasi balik sudah tertulis).
 *
 * Belum ada listener-nya sekarang. Ini titik gantung buat modul Invoice:
 * pembatalan menulis mutasi OUT bertanggal dokumen aslinya, jadi `stok_harian`
 * dari tanggal itu ke depan wajib dihitung ulang ("03-aturan-bisnis.md" §3.7).
 */
class PenerimaanDibatalkan
{
    use Dispatchable;

    public function __construct(public Penerimaan $penerimaan) {}
}
