<?php

namespace App\Policies;

use App\Enums\StatusPenerimaan;
use App\Models\Penerimaan;
use App\Models\User;

/**
 * Otorisasi per dokumen — bukan cuma filter di daftar.
 *
 * "08-keamanan.md" §2.1: endpoint detail, cetak PDF, dan aksi posting/batalkan
 * adalah tiga tempat yang paling sering lolos pengecekan. Semua method di
 * PenerimaanController memanggil salah satu policy di bawah.
 */
class PenerimaanPolicy
{
    public function view(User $user, Penerimaan $penerimaan): bool
    {
        return $this->gudangCocok($user, $penerimaan);
    }

    public function create(User $user): bool
    {
        return $user->role?->bisaTransaksi() === true
            && ($user->bisaAksesSemuaGudang() || $user->gudang_id !== null);
    }

    /** Cuma draft yang boleh diedit — dokumen ter-posting terkunci. */
    public function update(User $user, Penerimaan $penerimaan): bool
    {
        return $user->role?->bisaTransaksi() === true
            && $penerimaan->status->bisaDiedit()
            && $this->gudangCocok($user, $penerimaan);
    }

    public function posting(User $user, Penerimaan $penerimaan): bool
    {
        return $this->update($user, $penerimaan);
    }

    public function batalkan(User $user, Penerimaan $penerimaan): bool
    {
        return $user->role?->bisaMembatalkanDokumen() === true
            && $penerimaan->status === StatusPenerimaan::Posted
            && $this->gudangCocok($user, $penerimaan);
    }

    /**
     * Draft belum punya nomor resmi, jadi tidak ada yang boleh dicetak darinya —
     * kertas bernomor kosong yang beredar di lapangan cuma bikin kacau.
     */
    public function cetak(User $user, Penerimaan $penerimaan): bool
    {
        return $this->view($user, $penerimaan)
            && $penerimaan->status !== StatusPenerimaan::Draft;
    }

    /**
     * super_admin & operator_pusat lintas gudang; sisanya cuma gudang tempatnya
     * ditugaskan. User tanpa penempatan tidak melihat dokumen mana pun.
     */
    private function gudangCocok(User $user, Penerimaan $penerimaan): bool
    {
        if ($user->bisaAksesSemuaGudang()) {
            return true;
        }

        return $user->gudang_id !== null
            && (int) $user->gudang_id === (int) $penerimaan->gudang_id;
    }
}
