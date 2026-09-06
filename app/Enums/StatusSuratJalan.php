<?php

namespace App\Enums;

/**
 * Siklus status: draft -> posted -> diterima, dengan cabang posted -> dibatalkan.
 * Tidak ada `dikirim` dan tidak ada jalur mundur ("10-modul-surat-jalan.md" §10.1).
 *
 * Semua pertanyaan "boleh apa di status ini" dijawab lewat method di bawah,
 * supaya tidak ada perbandingan string status bertebaran di controller/view.
 */
enum StatusSuratJalan: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Diposting',
            self::Diterima => 'Diterima',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Warna badge tunggal biar konsisten di semua layar ("07-panduan-frontend.md" §9). */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-secondary-lt',
            self::Posted => 'bg-blue-lt',
            self::Diterima => 'bg-green-lt',
            self::Dibatalkan => 'bg-red-lt',
        };
    }

    /** Draft bebas diedit dan belum menyentuh stok ("10-modul-surat-jalan.md" §2.2). */
    public function bisaDiedit(): bool
    {
        return $this === self::Draft;
    }

    /** Posting = nomor terbit + mutasi OUT tertulis + dokumen terkunci. */
    public function bisaDiposting(): bool
    {
        return $this === self::Draft;
    }

    /** Kapan saja setelah posting, tanpa approval ("10-modul-surat-jalan.md" §10.6). */
    public function bisaDitandaiDiterima(): bool
    {
        return $this === self::Posted;
    }

    /**
     * Hanya dari `posted`. Begitu barang difisik diterima di lokasi, koreksi
     * harus lewat retur/susulan — bukan pembatalan ("10-modul-surat-jalan.md" §2.5).
     */
    public function bisaDibatalkan(): bool
    {
        return $this === self::Posted;
    }

    /** Draft belum bernomor resmi, jadi tidak ada yang boleh dicetak darinya. */
    public function bisaDicetak(): bool
    {
        return $this !== self::Draft;
    }

    /** Status yang dihitung sebagai "sudah terkirim" ("02-model-data.md" §4.2). */
    public static function terkirim(): array
    {
        return [self::Posted->value, self::Diterima->value];
    }
}
