<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Dipakai di controller yang menyentuh master data / dokumen supaya setiap
 * perubahan tercatat di activity_log — wajib sesuai "03-aturan-bisnis.md" §8
 * (perubahan master data, perubahan tarif, posting/pembatalan dokumen, dst).
 */
trait LogsActivity
{
    protected function catatAktivitas(
        string $aksi,
        ?Model $subjek,
        ?array $dataLama = null,
        ?array $dataBaru = null,
        ?string $deskripsi = null,
    ): void {
        ActivityLog::catat($aksi, $subjek, $dataLama, $dataBaru, $deskripsi);
    }
}
