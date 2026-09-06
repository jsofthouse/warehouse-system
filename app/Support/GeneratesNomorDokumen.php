<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Penerbitan nomor dokumen berurut per gudang per tahun.
 *
 * Aturannya di "03-aturan-bisnis.md" §2: nomor terbit saat POSTING (bukan saat
 * draft dibuat, supaya tidak ada nomor bolong) dan diterbitkan di dalam
 * transaksi dengan penguncian baris supaya dua operator yang posting bersamaan
 * tidak dapat nomor kembar.
 *
 * Dipakai bareng oleh Penerimaan, dan nanti Surat Jalan & Invoice — cukup ganti
 * $prefix, $tabel, dan $kolom.
 */
trait GeneratesNomorDokumen
{
    /**
     * Terbitkan nomor berikutnya dengan format {PREFIX}-{KODE_GUDANG}-{TAHUN}-{URUT 4 digit},
     * contoh: BM-SMG-2026-0001.
     *
     * WAJIB dipanggil di dalam DB::transaction() — kalau tidak, lockForUpdate()
     * tidak menahan apa pun dan nomor kembar cuma soal waktu.
     */
    protected function terbitkanNomorDokumen(
        string $prefix,
        string $kodeGudang,
        int $tahun,
        string $tabel,
        string $kolom,
    ): string {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'terbitkanNomorDokumen() harus dipanggil di dalam DB::transaction() — lihat "03-aturan-bisnis.md" §2.3.'
            );
        }

        // Semua segmen dinormalkan ke [A-Z0-9] supaya awalan tidak pernah memuat
        // wildcard LIKE (% atau _) dan pemisah "-" tetap tidak ambigu.
        $awalan = sprintf(
            '%s-%s-%d-',
            $this->segmenNomorDokumen($prefix),
            $this->segmenNomorDokumen($kodeGudang),
            $tahun,
        );

        // Kunci baris nomor terakhir dengan awalan yang sama. Dikunci per-awalan,
        // bukan per gudang_id, supaya dua gudang yang (karena salah input kode)
        // menghasilkan awalan sama tetap berbagi satu deret — tabrakan nomor
        // lebih mahal daripada deret yang menyatu.
        //
        // Urut pakai LENGTH dulu supaya deret tetap benar kalau suatu saat
        // tembus 4 digit ('10000' > '9999' secara angka, tapi tidak secara teks).
        $terakhir = DB::table($tabel)
            ->where($kolom, 'like', $awalan.'%')
            ->orderByRaw("LENGTH({$this->kolomNomorAman($kolom)}) desc")
            ->orderByDesc($kolom)
            ->lockForUpdate()
            ->value($kolom);

        $urut = $terakhir === null
            ? 1
            : ((int) substr((string) $terakhir, strlen($awalan))) + 1;

        return $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    private function segmenNomorDokumen(string $nilai): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($nilai)) ?: 'X';
    }

    /**
     * orderByRaw tidak bisa di-bind parameternya, jadi nama kolom dibersihkan
     * manual. Nilainya selalu konstanta di kode pemanggil, tapi pembersihan ini
     * menutup jalur injeksi kalau suatu saat ada yang mengoper input ke sini.
     *
     * Sengaja tanpa kutip identifier: backtick cuma sah di MySQL, kutip ganda
     * cuma sah di Postgres/SQLite. Nama kolom polos sah di ketiganya.
     */
    private function kolomNomorAman(string $kolom): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $kolom);
    }
}
