<?php

namespace App\Models;

use App\Enums\StatusInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'nomor_invoice',
        'gudang_id',
        'periode_mulai',
        'periode_selesai',
        'subtotal',
        'ppn_persen',
        'ppn_nominal',
        'total',
        'status',
        'tanggal_bayar',
        'keterangan',
        'created_by',
        'posted_by',
        'posted_at',
        'dibatalkan_by',
        'dibatalkan_at',
        'alasan_pembatalan',
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'subtotal' => 'decimal:2',
        'ppn_persen' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'total' => 'decimal:2',
        'status' => StatusInvoice::class,
        'tanggal_bayar' => 'date',
        'posted_at' => 'datetime',
        'dibatalkan_at' => 'datetime',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function scopeUntukGudang(Builder $query, int $gudangId): Builder
    {
        return $query->where('gudang_id', $gudangId);
    }
}
