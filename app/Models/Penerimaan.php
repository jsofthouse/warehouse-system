<?php

namespace App\Models;

use App\Enums\StatusPenerimaan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Penerimaan extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'penerimaan';

    /**
     * `nomor_penerimaan`, `status`, `posted_by/at`, dan `dibatalkan_by/at`
     * sengaja TIDAK ikut $fillable: semuanya diisi server lewat forceFill() di
     * PenerimaanController, tidak pernah dari request ("08-keamanan.md" §3).
     * `gudang_id` tetap fillable tapi nilainya ditentukan di PenerimaanRequest,
     * bukan diambil mentah dari input.
     */
    protected $fillable = [
        'gudang_id',
        'tanggal',
        'vendor_nama',
        'nomor_dokumen_vendor',
        'no_kontrak_referensi',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'status' => StatusPenerimaan::class,
        'posted_at' => 'datetime',
        'dibatalkan_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => StatusPenerimaan::Draft->value,
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function detail()
    {
        return $this->hasMany(PenerimaanDetail::class);
    }

    public function mutasi()
    {
        return $this->morphMany(StokMutasi::class, 'referensi');
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

    /** Scoping wajib buat operator_gudang — lihat User::bisaAksesSemuaGudang(). */
    public function scopeUntukGudang(Builder $query, int $gudangId): Builder
    {
        return $query->where('gudang_id', $gudangId);
    }

    public function totalUnit(): int
    {
        return (int) $this->detail->sum('jumlah');
    }
}
