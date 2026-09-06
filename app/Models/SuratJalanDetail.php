<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratJalanDetail extends Model
{
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
