<?php

namespace App\Support;

use App\Enums\BasisTarif;
use App\Enums\StatusPenerimaan;
use App\Exceptions\PerhitunganBiayaSewaGagal;
use App\Models\SuratJalan;
use App\Models\TarifSewa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hitung biaya sewa gudang satu surat jalan langsung dari tanggal dokumen,
 * tanpa alokasi per-batch ("04-invoice-sewa-gudang.md" §3b.1 — menggantikan
 * skema FIFO §3a yang sudah dorman). `tanggal_masuk_item` per item diambil
 * dari MIN(penerimaan.tanggal) seluruh Penerimaan berstatus posted untuk item
 * & gudang itu. Dipakai dua kali: buat pratinjau draft (tidak disimpan) dan
 * buat isi `invoice_detail` yang sesungguhnya (InvoiceController::store()) —
 * SELALU dihitung ulang penuh di server, tidak pernah dari angka yang tampil
 * di pratinjau (CLAUDE.md §10 poin 3).
 */
final class PerhitunganBiayaSewa
{
    /**
     * @return array{
     *     tarif: TarifSewa,
     *     periode_mulai: Carbon,
     *     periode_selesai: Carbon,
     *     baris: array<int, array<string, mixed>>,
     *     subtotal: float,
     *     ppn_persen: float,
     *     ppn_nominal: float,
     *     total: float,
     *     subtotal_pokok: float,
     * }
     *
     * @throws PerhitunganBiayaSewaGagal
     */
    public static function hitung(SuratJalan $suratJalan): array
    {
        $suratJalan->loadMissing(['detail.item', 'gudang']);

        $tarif = TarifSewa::query()
            ->berlakuPada((int) $suratJalan->gudang_id, $suratJalan->tanggal)
            ->first();

        if (! $tarif) {
            throw new PerhitunganBiayaSewaGagal(
                "Tidak ada tarif sewa yang berlaku untuk gudang {$suratJalan->gudang->nama} pada tanggal {$suratJalan->tanggal->toDateString()}."
            );
        }

        $itemIds = $suratJalan->detail->pluck('item_id')->unique()->values();

        $tanggalMasukPerItem = DB::table('penerimaan_detail')
            ->join('penerimaan', 'penerimaan.id', '=', 'penerimaan_detail.penerimaan_id')
            ->where('penerimaan.gudang_id', $suratJalan->gudang_id)
            ->where('penerimaan.status', StatusPenerimaan::Posted->value)
            ->whereIn('penerimaan_detail.item_id', $itemIds)
            ->selectRaw('penerimaan_detail.item_id as item_id, MIN(penerimaan.tanggal) as tanggal_masuk')
            ->groupBy('penerimaan_detail.item_id')
            ->pluck('tanggal_masuk', 'item_id');

        $baris = [];
        $tanggalMasukPalingAwal = null;

        foreach ($suratJalan->detail as $detail) {
            $item = $detail->item;

            if ($item === null || $item->berat_kg === null) {
                $nama = $item?->nama ?? "item #{$detail->item_id}";

                throw new PerhitunganBiayaSewaGagal(
                    "Item {$nama} belum punya berat_kg — lengkapi dulu di master item sebelum bikin invoice."
                );
            }

            $tanggalMasukMentah = $tanggalMasukPerItem->get($detail->item_id);

            if ($tanggalMasukMentah === null) {
                throw new PerhitunganBiayaSewaGagal(
                    "Item {$item->nama} tidak punya satupun Penerimaan berstatus posted di gudang {$suratJalan->gudang->nama} — barang masuknya kemungkinan belum diposting. Posting dulu dokumen Penerimaan terkait sebelum bikin invoice."
                );
            }

            $tanggalMasuk = Carbon::parse($tanggalMasukMentah);
            $hariSimpan = (int) $tanggalMasuk->diffInDays($suratJalan->tanggal);
            $totalUnitHari = $detail->jumlah_kirim * $hariSimpan;

            if ($tanggalMasukPalingAwal === null || $tanggalMasuk->lt($tanggalMasukPalingAwal)) {
                $tanggalMasukPalingAwal = $tanggalMasuk;
            }

            $beratKg = (float) $item->berat_kg;
            $totalSatuanHari = $totalUnitHari * $beratKg;

            $hargaJual = (float) $tarif->harga_jual_per_satuan_per_hari;
            $hargaBeli = (float) $tarif->harga_beli_per_satuan_per_hari;

            $subtotal = $totalSatuanHari * $hargaJual;
            $subtotalPokok = $totalSatuanHari * $hargaBeli;

            if ($tarif->pembulatan_rupiah) {
                $subtotal = round($subtotal / $tarif->pembulatan_rupiah) * $tarif->pembulatan_rupiah;
            }

            $baris[] = [
                'item_id' => $detail->item_id,
                'basis' => BasisTarif::KgAktual,
                'total_unit_hari' => $totalUnitHari,
                'satuan_per_unit' => $beratKg,
                'total_satuan_hari' => $totalSatuanHari,
                'harga_jual_per_satuan_per_hari' => $hargaJual,
                'harga_beli_per_satuan_per_hari' => $hargaBeli,
                'subtotal' => $subtotal,
                'subtotal_pokok' => $subtotalPokok,
            ];
        }

        $subtotalInvoice = array_sum(array_column($baris, 'subtotal'));
        $subtotalPokokInvoice = array_sum(array_column($baris, 'subtotal_pokok'));
        $ppnPersen = (float) $tarif->ppn_persen;
        $ppnNominal = $subtotalInvoice * ($ppnPersen / 100);

        return [
            'tarif' => $tarif,
            'periode_mulai' => $tanggalMasukPalingAwal,
            'periode_selesai' => $suratJalan->tanggal,
            'baris' => $baris,
            'subtotal' => $subtotalInvoice,
            'ppn_persen' => $ppnPersen,
            'ppn_nominal' => $ppnNominal,
            'total' => $subtotalInvoice + $ppnNominal,
            'subtotal_pokok' => $subtotalPokokInvoice,
        ];
    }
}
