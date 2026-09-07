<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PihakTertagih extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'pihak_tertagih';

    protected $fillable = [
        'nama',
        'alamat',
        'npwp',
    ];
}
