<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'gudang';

    protected $fillable = [
        'kode',
        'nama',
        'kota',
        'alamat',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Segmen kode yang dipakai di nomor dokumen: 'GDG-SMG' jadi 'SMG', sehingga
     * nomornya terbaca BM-SMG-2026-0001 dan pemisah "-" tidak ambigu.
     * Lihat App\Support\GeneratesNomorDokumen.
     */
    public function kodeDokumen(): string
    {
        $kode = (string) $this->kode;
        $segmen = str_contains($kode, '-') ? substr(strrchr($kode, '-'), 1) : $kode;

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($segmen)) ?: 'GDG';
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function tarifSewas()
    {
        return $this->hasMany(TarifSewa::class);
    }

    public function penerimaans()
    {
        return $this->hasMany(Penerimaan::class);
    }

    public function suratJalans()
    {
        return $this->hasMany(SuratJalan::class);
    }

    public function stokMutasis()
    {
        return $this->hasMany(StokMutasi::class);
    }

    public function stokHarians()
    {
        return $this->hasMany(StokHarian::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
