<?php

namespace App\Models;

use App\Enums\BasisTarif;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TarifSewa extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'tarif_sewa';

    protected $fillable = [
        'gudang_id',
        'basis',
        'tarif_per_satuan_per_hari',
        'faktor_volumetrik_kg_per_m3',
        'ppn_persen',
        'min_hari_simpan',
        'pembulatan_rupiah',
        'berlaku_mulai',
        'berlaku_sampai',
    ];

    protected $casts = [
        'basis' => BasisTarif::class,
        'tarif_per_satuan_per_hari' => 'decimal:2',
        'faktor_volumetrik_kg_per_m3' => 'decimal:2',
        'ppn_persen' => 'decimal:2',
        'min_hari_simpan' => 'integer',
        'pembulatan_rupiah' => 'integer',
        'berlaku_mulai' => 'date',
        'berlaku_sampai' => 'date',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    /** Tarif yang berlaku buat satu tanggal tertentu di satu gudang. */
    public function scopeBerlakuPada(Builder $query, int $gudangId, \DateTimeInterface|string $tanggal): Builder
    {
        return $query->where('gudang_id', $gudangId)
            ->where('berlaku_mulai', '<=', $tanggal)
            ->where(function (Builder $q) use ($tanggal) {
                $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $tanggal);
            });
    }
}
