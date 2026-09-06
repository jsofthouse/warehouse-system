<?php

namespace App\Support;

use App\Enums\StatusSuratJalan;
use App\Enums\TipeMutasiStok;
use App\Models\AlokasiKebutuhan;
use App\Models\Item;
use App\Models\StokMutasi;
use App\Models\SuratJalanDetail;
use Illuminate\Support\Collection;

/**
 * Empat angka pembanding di layar buat surat jalan: alokasi, sudah kirim,
 * sisa, dan stok gudang — plus batas keras kolom Kirim
 * ("10-modul-surat-jalan.md" §4.2, query acuan "02-model-data.md" §4.2).
 *
 * Dipakai bareng oleh SuratJalanRequest (validasi saat draft disimpan),
 * SuratJalanController::pembanding() (isi tabel lewat AJAX), dan
 * SuratJalanController::posting() (hitung ulang di dalam transaksi). Satu
 * sumber hitungan, jadi tidak ada peluang tiga tempat itu jawab beda.
 *
 * Angka di sini TIDAK PERNAH datang dari client — apa pun yang dikirim browser
 * cuma dipakai sebagai jumlah yang diminta, batasnya selalu dihitung ulang.
 */
final class KetersediaanPengiriman
{
    /**
     * @return Collection<int, object{
     *     item: Item, alokasi: int, sudah_kirim: int, sisa: int,
     *     stok_gudang: int, batas: int
     * }> di-key oleh item_id
     */
    public static function untuk(int $gudangId, int $lokasiId): Collection
    {
        $items = Item::query()
            ->where('is_active', true)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'satuan']);

        $alokasi = AlokasiKebutuhan::query()
            ->where('lokasi_id', $lokasiId)
            ->pluck('jumlah_kebutuhan', 'item_id');

        $sudahKirim = self::sudahTerkirim($lokasiId);
        $stok = self::stokGudang($gudangId);

        return $items->mapWithKeys(function (Item $item) use ($alokasi, $sudahKirim, $stok) {
            $jumlahAlokasi = (int) ($alokasi[$item->id] ?? 0);
            $jumlahKirim = (int) ($sudahKirim[$item->id] ?? 0);
            $sisa = max(0, $jumlahAlokasi - $jumlahKirim);
            $stokGudang = max(0, (int) ($stok[$item->id] ?? 0));

            return [$item->id => (object) [
                'item' => $item,
                'alokasi' => $jumlahAlokasi,
                'sudah_kirim' => $jumlahKirim,
                'sisa' => $sisa,
                'stok_gudang' => $stokGudang,
                // Batas keras kolom Kirim: sisa alokasi DAN stok, dua-duanya
                // ditolak keras kalau dilewati ("10-modul-surat-jalan.md" §10.2).
                'batas' => min($sisa, $stokGudang),
            ]];
        });
    }

    /**
     * Total unit yang sudah terkirim ke satu lokasi, dari surat jalan berstatus
     * posted/diterima. Draft tidak dihitung — draft belum memotong apa pun.
     *
     * Tidak difilter per gudang: alokasi itu milik lokasi, jadi kiriman dari
     * gudang mana pun ikut mengurangi sisanya.
     *
     * @return Collection<int, int>
     */
    private static function sudahTerkirim(int $lokasiId): Collection
    {
        return SuratJalanDetail::query()
            ->join('surat_jalan', 'surat_jalan.id', '=', 'surat_jalan_detail.surat_jalan_id')
            ->where('surat_jalan.lokasi_id', $lokasiId)
            ->whereIn('surat_jalan.status', StatusSuratJalan::terkirim())
            ->groupBy('surat_jalan_detail.item_id')
            ->selectRaw('surat_jalan_detail.item_id as item_id, SUM(surat_jalan_detail.jumlah_kirim) as total')
            ->pluck('total', 'item_id')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Stok on-hand = SUM(IN) - SUM(OUT) per item di gudang ini. Dihitung dari
     * ledger `stok_mutasi`, tidak pernah dari kolom stok yang di-UPDATE
     * (CLAUDE.md §5.1 / "03-aturan-bisnis.md" §3).
     *
     * @return Collection<int, int>
     */
    private static function stokGudang(int $gudangId): Collection
    {
        return StokMutasi::query()
            ->where('gudang_id', $gudangId)
            ->groupBy('item_id')
            ->selectRaw(
                'item_id, SUM(CASE WHEN tipe = ? THEN jumlah ELSE -jumlah END) as stok',
                [TipeMutasiStok::In->value],
            )
            ->pluck('stok', 'item_id')
            ->map(fn ($stok) => (int) $stok);
    }
}
