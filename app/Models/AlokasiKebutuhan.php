<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlokasiKebutuhan extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'alokasi_kebutuhan';

    protected $fillable = [
        'lokasi_id',
        'item_id',
        'jumlah_kebutuhan',
    ];

    protected $casts = [
        'jumlah_kebutuhan' => 'integer',
    ];

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
