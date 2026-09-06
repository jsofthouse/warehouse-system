<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'activity_log';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'aksi',
        'subjek_type',
        'subjek_id',
        'deskripsi',
        'data_lama',
        'data_baru',
        'ip_address',
    ];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subjek()
    {
        return $this->morphTo();
    }

    /**
     * Tulis satu baris activity_log. Badan logic ini sebelumnya ada di
     * LogsActivity::catatAktivitas() (trait, cuma bisa dipanggil dari
     * controller lewat $this->) — dipindah ke sini supaya Listener (bukan
     * controller, tidak punya $this yang relevan) juga bisa memakainya.
     * Signature dan perilaku sama persis, trait tinggal mendelegasikan.
     */
    public static function catat(
        string $aksi,
        ?Model $subjek,
        ?array $dataLama = null,
        ?array $dataBaru = null,
        ?string $deskripsi = null,
    ): void {
        // $subjek nullable buat aksi yang tidak menempel ke satu baris tertentu,
        // misal "set alokasi massal ke semua lokasi".
        static::create([
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
