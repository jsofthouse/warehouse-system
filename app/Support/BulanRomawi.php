<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Konversi bulan (1-12) ke angka romawi.
 *
 * Dipisah dari GeneratesNomorDokumen supaya bisa dipakai di luar penerbitan
 * nomor juga — Surat Jalan memakainya sekarang (SJ/SMG/0042/IX/2026), Invoice
 * memakai pola yang sama nanti ("10-modul-surat-jalan.md" §10.3 & §11.4).
 */
final class BulanRomawi
{
    private const ANGKA = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public static function dari(int $bulan): string
    {
        return self::ANGKA[$bulan]
            ?? throw new InvalidArgumentException("Bulan {$bulan} di luar rentang 1-12.");
    }
}
