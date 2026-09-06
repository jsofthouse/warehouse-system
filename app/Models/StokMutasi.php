<?php

namespace App\Models;

use App\Enums\TipeMutasiStok;
use Illuminate\Database\Eloquent\Model;

class StokMutasi extends Model
{
    // Ledger append-only: nggak ada updated_at, dan lewat kode nggak pernah di-update/delete.
    const UPDATED_AT = null;

    protected $fillable = [
        'gudang_id',
        'item_id',
        'tanggal',
        'tipe',
        'jumlah',
        'referensi_type',
        'referensi_id',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tipe' => TipeMutasiStok::class,
        'jumlah' => 'integer',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function referensi()
    {
        return $this->morphTo();
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
