<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat draft invoice tidak bisa dihitung — item belum py berat_kg,
 * atau tidak ada tarif sewa yang berlaku ("04-invoice-sewa-gudang.md" §3a.5).
 * Pesannya aman ditampilkan langsung ke operator.
 */
class PerhitunganBiayaSewaGagal extends RuntimeException
{
    //
}
