<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case OperatorPusat = 'operator_pusat';
    case OperatorGudang = 'operator_gudang';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OperatorPusat => 'Operator Pusat',
            self::OperatorGudang => 'Operator Gudang',
            self::Viewer => 'Manajemen / Viewer',
        };
    }

    /** Role yang aksesnya lintas-gudang (nggak di-scope ke satu gudang_id). */
    public function isLintasGudang(): bool
    {
        return in_array($this, [self::SuperAdmin, self::OperatorPusat], true);
    }

    /**
     * Role yang boleh menulis (tambah/ubah/hapus) master data — Gudang, Item,
     * Lokasi, Alokasi, Tarif Sewa. Lihat matriks hak akses di
     * "Spesifikasi Sistem Gudang Alkap.md" §4: cuma Super Admin & Operator Pusat.
     */
    public function bisaKelolaMasterData(): bool
    {
        return $this->isLintasGudang();
    }

    /**
     * Role yang boleh menulis dokumen transaksi (bikin draft, edit, posting).
     * viewer baca saja, termasuk lewat endpoint — "03-aturan-bisnis.md" §7.5.
     */
    public function bisaTransaksi(): bool
    {
        return $this !== self::Viewer;
    }

    /**
     * Pembatalan dokumen ter-posting cuma operator_pusat ke atas
     * ("03-aturan-bisnis.md" §7.3).
     */
    public function bisaMembatalkanDokumen(): bool
    {
        return $this->isLintasGudang();
    }
}
