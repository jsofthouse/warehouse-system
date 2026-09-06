<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
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

    public function alokasiKebutuhans()
    {
        return $this->hasMany(AlokasiKebutuhan::class);
    }

    public function penerimaanDetails()
    {
        return $this->hasMany(PenerimaanDetail::class);
    }

    public function suratJalanDetails()
    {
        return $this->hasMany(SuratJalanDetail::class);
    }

    public function stokMutasis()
    {
        return $this->hasMany(StokMutasi::class);
    }

    public function stokHarians()
    {
        return $this->hasMany(StokHarian::class);
    }
}
