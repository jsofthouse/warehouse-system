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
        // $subjek nullable buat aksi yang tidak menempel ke satu baris tertentu,
        // misal "set alokasi massal ke semua lokasi".
        ActivityLog::create([
            'user_id' => auth()->id(),
            'aksi' => $aksi,
            'subjek_type' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
            'deskripsi' => $deskripsi,
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
            'ip_address' => request()?->ip(),
        ]);
    }
}
