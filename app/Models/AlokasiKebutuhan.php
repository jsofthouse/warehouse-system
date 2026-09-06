<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlokasiKebutuhan extends Model
{
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
