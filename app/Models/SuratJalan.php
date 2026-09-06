<?php

namespace App\Models;

use App\Enums\StatusSuratJalan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SuratJalan extends Model
{
    protected $fillable = [
        'nomor_surat_jalan',
        'gudang_id',
        'lokasi_id',
        'tanggal',
        'ekspedisi',
        'nomor_polisi',
        'nama_sopir',
        'status',
        'tanggal_terima',
        'nomor_bast',
        'created_by',
        'posted_by',
        'posted_at',
        'dibatalkan_by',
        'dibatalkan_at',
        'alasan_pembatalan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_terima' => 'date',
        'status' => StatusSuratJalan::class,
        'posted_at' => 'datetime',
        'dibatalkan_at' => 'datetime',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class);
    }

    public function details()
    {
        return $this->hasMany(SuratJalanDetail::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function pembatal()
    {
        return $this->belongsTo(User::class, 'dibatalkan_by');
    }

    public function scopeUntukGudang(Builder $query, int $gudangId): Builder
    {
        return $query->where('gudang_id', $gudangId);
    }
}
