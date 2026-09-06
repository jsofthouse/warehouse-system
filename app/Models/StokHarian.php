<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokHarian extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'stok_harian';

    protected $fillable = [
        'gudang_id',
        'item_id',
        'tanggal',
        'stok_akhir',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'stok_akhir' => 'integer',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
