<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'lokasi';

    protected $fillable = [
        'kode',
        'nama_kodim',
        'provinsi',
        'kabupaten',
        'kecamatan',
        'desa',
        'alamat',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function daftarAlokasiKebutuhan()
    {
        return $this->hasMany(AlokasiKebutuhan::class);
    }

    public function daftarSuratJalan()
    {
        return $this->hasMany(SuratJalan::class);
    }
}
