<?php

namespace App\Support;

use App\Enums\BasisTarif;
use App\Exceptions\PerhitunganBiayaSewaGagal;
use App\Models\AlokasiBatchKeluar;
use App\Models\SuratJalan;
use App\Models\TarifSewa;
use Illuminate\Support\Carbon;

/**
 * Hitung biaya sewa gudang satu surat jalan dari baris `alokasi_batch_keluar`
 * yang sudah tertulis saat posting ("04-invoice-sewa-gudang.md" §3a.5). Dipakai
 * dua kali: buat pratinjau draft (tidak disimpan) dan buat isi `invoice_detail`
 * yang sesungguhnya (InvoiceController::store()) — SELALU dihitung ulang penuh
 * di server, tidak pernah dari angka yang tampil di pratinjau (CLAUDE.md §10).
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

            $alokasi = AlokasiBatchKeluar::query()
                ->where('surat_jalan_detail_id', $detail->id)
                ->with('penerimaanDetail.penerimaan')
                ->get();

            if ($alokasi->isEmpty()) {
                throw new PerhitunganBiayaSewaGagal(
                    "Baris {$item->nama} belum punya alokasi batch — surat jalan ini kemungkinan diposting sebelum mesin FIFO ada."
                );
            }

            $totalUnitHari = 0;

            foreach ($alokasi as $satuAlokasi) {
                $tanggalMasuk = $satuAlokasi->penerimaanDetail->penerimaan->tanggal;
                $hariSimpan = (int) $tanggalMasuk->diffInDays($suratJalan->tanggal);
                $totalUnitHari += $satuAlokasi->qty_dialokasikan * $hariSimpan;

                if ($tanggalMasukPalingAwal === null || $tanggalMasuk->lt($tanggalMasukPalingAwal)) {
                    $tanggalMasukPalingAwal = $tanggalMasuk;
                }
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
