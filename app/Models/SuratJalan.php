<?php

namespace App\Models;

use App\Enums\StatusSuratJalan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SuratJalan extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'surat_jalan';

    /**
     * `nomor_surat_jalan`, `status`, `posted_by/at`, `dibatalkan_by/at`,
     * `tanggal_terima`, `nomor_bast`, dan `nama_penerima` sengaja TIDAK ikut
     * $fillable: semuanya diisi server lewat forceFill() di
     * SuratJalanController, tidak pernah lewat mass assignment dari request
     * ("08-keamanan.md" §3 — pola sama dengan Penerimaan).
     *
     * `gudang_id` tetap fillable tapi nilainya ditentukan di SuratJalanRequest,
     * bukan diambil mentah dari input.
     */
    protected $fillable = [
        'gudang_id',
        'lokasi_id',
        'tanggal',
        'ekspedisi',
        'nomor_polisi',
        'nama_sopir',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_terima' => 'date',
        'status' => StatusSuratJalan::class,
        'posted_at' => 'datetime',
        'dibatalkan_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => StatusSuratJalan::Draft->value,
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class);
    }

    public function detail()
    {
        return $this->hasMany(SuratJalanDetail::class);
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

    /** Dokumen yang dihitung sebagai "sudah terkirim" ("02-model-data.md" §4.2). */
    public function scopeTerkirim(Builder $query): Builder
    {
        return $query->whereIn('status', StatusSuratJalan::terkirim());
    }

    public function totalUnit(): int
    {
        return (int) $this->detail->sum('jumlah_kirim');
    }
}
