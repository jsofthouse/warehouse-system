<?php

namespace App\Enums;

enum StatusPenerimaan: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Diposting',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Pemetaan warna badge tunggal biar konsisten di semua layar — lihat
     * "07-panduan-frontend.md" §9: draft abu-abu, posted biru, dibatalkan merah.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-secondary-lt',
            self::Posted => 'bg-blue-lt',
            self::Dibatalkan => 'bg-red-lt',
        };
    }

    /** Draft = bebas diedit dan belum menyentuh stok ("03-aturan-bisnis.md" §1.3). */
    public function bisaDiedit(): bool
    {
        return $this === self::Draft;
    }
}
