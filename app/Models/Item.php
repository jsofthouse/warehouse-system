<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'item';

    protected $fillable = [
        'kode',
        'nama',
        'satuan',
        'berat_kg',
        'panjang_cm',
        'lebar_cm',
        'tinggi_cm',
        'volume_m3',
        'is_active',
    ];

    protected $casts = [
        'berat_kg' => 'decimal:3',
        'panjang_cm' => 'decimal:2',
        'lebar_cm' => 'decimal:2',
        'tinggi_cm' => 'decimal:2',
        'volume_m3' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function daftarAlokasiKebutuhan()
    {
        return $this->hasMany(AlokasiKebutuhan::class);
    }

    public function daftarPenerimaanDetail()
    {
        return $this->hasMany(PenerimaanDetail::class);
    }

    public function daftarSuratJalanDetail()
    {
        return $this->hasMany(SuratJalanDetail::class);
    }

    public function daftarStokMutasi()
    {
        return $this->hasMany(StokMutasi::class);
    }

    public function daftarStokHarian()
    {
        return $this->hasMany(StokHarian::class);
    }
}
