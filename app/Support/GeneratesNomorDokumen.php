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
 * Dua pola dipakai di sistem ini ("10-modul-surat-jalan.md" §10.3):
 * - Barang Masuk  : BM-SMG-2026-0001        (arsip internal gudang)
 * - Surat Jalan   : SJ/SMG/0042/IX/2026     (dibawa ke pihak luar, kesan surat resmi)
 * - Invoice nanti : pola romawi yang sama dengan Surat Jalan
 *
 * Karena itu inti penomorannya dikerjakan terhadap sebuah POLA berisi
 * placeholder, bukan satu susunan dash yang dipatok.
 */
trait GeneratesNomorDokumen
{
    /**
     * Pola lama Barang Masuk: {PREFIX}-{KODE_GUDANG}-{TAHUN}-{URUT 4 digit},
     * contoh BM-SMG-2026-0001. Deret-nya per prefix + gudang + tahun.
     */
    protected function terbitkanNomorDokumen(
        string $prefix,
        string $kodeGudang,
        int $tahun,
        string $tabel,
        string $kolom,
    ): string {
        return $this->terbitkanNomorDokumenBerpola(
            '{prefix}-{gudang}-{tahun}-{urut}',
            ['prefix' => $prefix, 'gudang' => $kodeGudang, 'tahun' => $tahun],
            ['prefix', 'gudang', 'tahun'],
            $tabel,
            $kolom,
        );
    }

    /**
     * Terbitkan nomor berikutnya untuk sebuah pola berisi placeholder.
     *
     * Contoh Surat Jalan:
     *   terbitkanNomorDokumenBerpola(
     *       'SJ/{gudang}/{urut}/{romawi}/{tahun}',
     *       ['gudang' => 'SMG', 'romawi' => 'IX', 'tahun' => 2026],
     *       ['gudang', 'tahun'],           // bulan TIDAK mengunci deret
     *       'surat_jalan', 'nomor_surat_jalan',
     *   );  // -> SJ/SMG/0042/IX/2026
     *
     * $kunciDeret menentukan segmen mana yang membentuk satu deret nomor.
     * Segmen di luar daftar itu (mis. bulan romawi) ikut berubah isinya tapi
     * tidak memecah deret — urut tetap lanjut dari bulan sebelumnya.
     *
     * WAJIB dipanggil di dalam DB::transaction() — kalau tidak, lockForUpdate()
     * tidak menahan apa pun dan nomor kembar cuma soal waktu.
     *
     * @param  array<string, string|int>  $segmen  nilai tiap placeholder selain {urut}
     * @param  array<int, string>  $kunciDeret  placeholder yang mengunci deret
     */
    protected function terbitkanNomorDokumenBerpola(
        string $pola,
        array $segmen,
        array $kunciDeret,
        string $tabel,
        string $kolom,
        int $lebarUrut = 4,
    ): string {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'terbitkanNomorDokumen() harus dipanggil di dalam DB::transaction() — lihat "03-aturan-bisnis.md" §2.3.'
            );
        }

        // Semua segmen dinormalkan ke [A-Z0-9] supaya pola tidak pernah memuat
        // wildcard LIKE (% atau _) dan pemisah "-" / "/" tetap tidak ambigu.
        $nilai = [];

        foreach ($segmen as $nama => $isi) {
            $nilai[$nama] = $this->segmenNomorDokumen((string) $isi);
        }

        [$polaLike, $polaRegex] = $this->susunPencocok($pola, $nilai, $kunciDeret);

        // Kunci semua nomor yang sudah terpakai di deret ini. Dikunci per-pola,
        // bukan per gudang_id, supaya dua gudang yang (karena salah input kode)
        // menghasilkan segmen sama tetap berbagi satu deret — tabrakan nomor
        // lebih mahal daripada deret yang menyatu.
        $terpakai = DB::table($tabel)
            ->whereNotNull($kolom)
            ->where($kolom, 'like', $polaLike)
            ->lockForUpdate()
            ->pluck($kolom);

        // Urut dibaca balik dari teks nomornya, lalu dibandingkan sebagai
        // ANGKA — bukan sebagai teks — supaya deret tetap benar setelah tembus
        // 4 digit ('10000' > '9999' secara angka, tapi tidak secara teks).
        $tertinggi = 0;

        foreach ($terpakai as $nomor) {
            if (preg_match($polaRegex, (string) $nomor, $cocok)) {
                $tertinggi = max($tertinggi, (int) $cocok['urut']);
            }
        }

        $nilai['urut'] = str_pad((string) ($tertinggi + 1), $lebarUrut, '0', STR_PAD_LEFT);

        return preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $cocok) => $nilai[$cocok[1]] ?? '',
            $pola,
        );
    }

    /**
     * Ubah pola jadi sepasang pencocok: pattern LIKE buat mempersempit query,
     * dan regex buat membaca balik segmen {urut} dari nomor yang sudah ada.
     *
     * @param  array<string, string>  $nilai
     * @param  array<int, string>  $kunciDeret
     * @return array{0: string, 1: string}
     */
    private function susunPencocok(string $pola, array $nilai, array $kunciDeret): array
    {
        $bagian = preg_split('/(\{[a-z_]+\})/', $pola, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $like = '';
        $regex = '';

        foreach ($bagian as $potong) {
            if (! preg_match('/^\{([a-z_]+)\}$/', $potong, $cocok)) {
                // Teks mati di pola (pemisah "-" atau "/") — pola selalu
                // konstanta di kode, tidak pernah datang dari input pengguna.
                $like .= $potong;
                $regex .= preg_quote($potong, '/');

                continue;
            }

            $nama = $cocok[1];

            if ($nama === 'urut') {
                $like .= '%';
                $regex .= '(?P<urut>\d+)';
            } elseif (in_array($nama, $kunciDeret, true)) {
                $like .= $nilai[$nama] ?? '';
                $regex .= preg_quote($nilai[$nama] ?? '', '/');
            } else {
                // Segmen yang boleh berbeda di dalam satu deret, mis. bulan.
                $like .= '%';
                $regex .= '[A-Z0-9]+';
            }
        }

        return [$like, '/^'.$regex.'$/'];
    }

    private function segmenNomorDokumen(string $nilai): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($nilai)) ?: 'X';
    }
}
