<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratJalanDetail extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'surat_jalan_detail';

    protected $fillable = [
        'surat_jalan_id',
        'item_id',
        'jumlah_kirim',
    ];

    protected $casts = [
        'jumlah_kirim' => 'integer',
    ];

    public function suratJalan()
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
