<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokHarian extends Model
{
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
