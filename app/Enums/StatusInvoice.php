<?php

namespace App\Enums;

enum StatusInvoice: string
{
    case Draft = 'draft';
    case Terbit = 'terbit';
    case Terbayar = 'terbayar';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Terbit => 'Terbit',
            self::Terbayar => 'Terbayar',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Warna badge tunggal biar konsisten di semua layar ("07-panduan-frontend.md" §9). */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-secondary-lt',
            self::Terbit => 'bg-blue-lt',
            self::Terbayar => 'bg-green-lt',
            self::Dibatalkan => 'bg-red-lt',
        };
    }
}
