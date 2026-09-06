<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanDetail extends Model
{
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
}
