<?php

namespace App\Models;

use App\Enums\BasisTarif;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    protected $fillable = [
        'invoice_id',
        'item_id',
        'basis',
        'total_unit_hari',
        'satuan_per_unit',
        'total_satuan_hari',
        'tarif_per_satuan_per_hari',
        'subtotal',
    ];

    protected $casts = [
        'basis' => BasisTarif::class,
        'total_unit_hari' => 'decimal:2',
        'satuan_per_unit' => 'decimal:4',
        'total_satuan_hari' => 'decimal:4',
        'tarif_per_satuan_per_hari' => 'decimal:2',
        'subtotal' => 'decimal:2',
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
