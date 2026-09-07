<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanDetail extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'penerimaan_detail';

    protected $fillable = [
        'penerimaan_id',
        'item_id',
        'jumlah',
        'keterangan',
    ];

    protected $casts = [
        'jumlah' => 'integer',
    ];

    public function penerimaan()
    {
        return $this->belongsTo(Penerimaan::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /** Baris alokasi FIFO yang menarik unit dari batch ini (§3a dokumen invoice). */
    public function daftarAlokasiBatchKeluar()
    {
        return $this->hasMany(AlokasiBatchKeluar::class);
    }
}
