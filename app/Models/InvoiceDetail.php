<?php

namespace App\Models;

use App\Enums\BasisTarif;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'invoice_detail';

    protected $fillable = [
        'invoice_id',
        'item_id',
        'basis',
        'total_unit_hari',
        'satuan_per_unit',
        'total_satuan_hari',
        'harga_jual_per_satuan_per_hari',
        'harga_beli_per_satuan_per_hari',
        'subtotal',
        'subtotal_pokok',
    ];

    protected $casts = [
        'basis' => BasisTarif::class,
        'total_unit_hari' => 'decimal:2',
        'satuan_per_unit' => 'decimal:4',
        'total_satuan_hari' => 'decimal:4',
        'harga_jual_per_satuan_per_hari' => 'decimal:2',
        'harga_beli_per_satuan_per_hari' => 'decimal:2',
        'subtotal' => 'decimal:2',
        // Biaya pokok internal (cost, dari harga_beli) — cuma buat laporan
        // margin, tidak pernah tampil di invoice cetak.
        'subtotal_pokok' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
