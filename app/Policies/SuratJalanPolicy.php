<?php

namespace App\Policies;

use App\Models\SuratJalan;
use App\Models\User;

/**
 * Otorisasi per dokumen — bukan cuma filter di daftar.
 *
 * "08-keamanan.md" §2.1 dan "10-modul-surat-jalan.md" §11.9: endpoint detail,
 * cetak PDF, posting, dan tandai-diterima adalah tempat yang paling sering
 * lolos pengecekan. Semua method di SuratJalanController memanggil salah satu
 * policy di bawah.
 *
 * Matriks hak aksesnya ada di "10-modul-surat-jalan.md" §6.
 */
class SuratJalanPolicy
{
    public function view(User $user, SuratJalan $suratJalan): bool
    {
        return $this->gudangCocok($user, $suratJalan);
    }

    public function create(User $user): bool
    {
        return $user->role?->bisaTransaksi() === true
            && ($user->bisaAksesSemuaGudang() || $user->gudang_id !== null);
    }

    /** Cuma draft yang boleh diedit — dokumen ter-posting terkunci. */
    public function update(User $user, SuratJalan $suratJalan): bool
    {
        return $user->role?->bisaTransaksi() === true
            && $suratJalan->status->bisaDiedit()
            && $this->gudangCocok($user, $suratJalan);
    }

    public function posting(User $user, SuratJalan $suratJalan): bool
    {
        return $user->role?->bisaTransaksi() === true
            && $suratJalan->status->bisaDiposting()
            && $this->gudangCocok($user, $suratJalan);
    }

    /**
     * Operator Gudang asal juga boleh — aksi ini cuma mengubah metadata, stok
     * sudah kepotong sejak posting ("10-modul-surat-jalan.md" §10.6).
     */
    public function tandaiDiterima(User $user, SuratJalan $suratJalan): bool
    {
        return $user->role?->bisaTransaksi() === true
            && $suratJalan->status->bisaDitandaiDiterima()
            && $this->gudangCocok($user, $suratJalan);
    }

    /** Pembatalan: cuma Op. Pusat & Super Admin, dan cuma dari status posted. */
    public function batalkan(User $user, SuratJalan $suratJalan): bool
    {
        return $user->role?->bisaMembatalkanDokumen() === true
            && $suratJalan->status->bisaDibatalkan()
            && $this->gudangCocok($user, $suratJalan);
    }

    /**
     * Draft belum punya nomor resmi, jadi tidak ada yang boleh dicetak darinya —
     * kertas bernomor kosong yang beredar di lapangan cuma bikin kacau.
     */
    public function cetak(User $user, SuratJalan $suratJalan): bool
    {
        return $this->view($user, $suratJalan)
            && $suratJalan->status->bisaDicetak();
    }

    /**
     * super_admin & operator_pusat lintas gudang; sisanya cuma gudang tempatnya
     * ditugaskan. User tanpa penempatan tidak melihat dokumen mana pun.
     */
    private function gudangCocok(User $user, SuratJalan $suratJalan): bool
    {
        if ($user->bisaAksesSemuaGudang()) {
            return true;
        }

        return $user->gudang_id !== null
            && (int) $user->gudang_id === (int) $suratJalan->gudang_id;
    }
}
