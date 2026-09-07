<?php

namespace App\Models;

use App\Enums\StatusInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'invoice';

    // Sama seperti Penerimaan/SuratJalan: kolomnya sendiri sudah default
    // 'draft' di database, tapi tanpa ini instance PHP hasil create() punya
    // status null sampai di-refresh() — pemanggil yang langsung baca
    // $invoice->status sesudah create() (mis. activity_log) akan meledak.
    protected $attributes = [
        'status' => StatusInvoice::Draft->value,
    ];

    protected $fillable = [
        'nomor_invoice',
        'gudang_id',
        'surat_jalan_id',
        'periode_mulai',
        'periode_selesai',
        'subtotal',
        'ppn_persen',
        'ppn_nominal',
        'total',
        'status',
        'tanggal_bayar',
        'keterangan',
        // Snapshot pihak tertagih, diisi manual per invoice — bukan referensi
        // ke master data ("04-invoice-sewa-gudang.md" §10).
        'nama_tertagih',
        'alamat_tertagih',
        'npwp_tertagih',
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

    public function suratJalan()
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function detail()
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

    public function pembatal()
    {
        return $this->belongsTo(User::class, 'dibatalkan_by');
    }

    public function scopeUntukGudang(Builder $query, int $gudangId): Builder
    {
        return $query->where('gudang_id', $gudangId);
    }
}
