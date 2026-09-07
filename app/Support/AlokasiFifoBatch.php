<?php

namespace App\Support;

use App\Enums\StatusPenerimaan;
use App\Enums\StatusSuratJalan;
use App\Models\AlokasiBatchKeluar;
use App\Models\SuratJalanDetail;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alokasi FIFO: saat surat jalan diposting, tiap baris detailnya menarik unit
 * dari batch (`penerimaan_detail` yang sudah posted) item yang sama di gudang
 * yang sama, dimulai dari tanggal masuk paling lama ("04-invoice-sewa-gudang.md"
 * §3a).
 *
 * WAJIB dipanggil di dalam DB::transaction() milik SuratJalanController::posting(),
 * SETELAH baris `gudang` dikunci (Gudang::lockForUpdate()). Lock gudang itu
 * sudah membariskan (serialize) semua posting surat jalan untuk gudang yang
 * sama satu per satu — itu yang mencegah dua posting membaca "sisa batch" yang
 * sama secara bersamaan dan mengalokasikan dobel (§3a.4). Query di sini tetap
 * memakai lockForUpdate() sendiri sebagai lapis kedua yang eksplisit.
 */
final class AlokasiFifoBatch
{
    public static function alokasikan(SuratJalanDetail $suratJalanDetail, int $gudangId): void
    {
        $dibutuhkan = $suratJalanDetail->jumlah_kirim;

        $daftarBatch = DB::table('penerimaan_detail')
            ->join('penerimaan', 'penerimaan.id', '=', 'penerimaan_detail.penerimaan_id')
            ->where('penerimaan.gudang_id', $gudangId)
            ->where('penerimaan.status', StatusPenerimaan::Posted->value)
            ->where('penerimaan_detail.item_id', $suratJalanDetail->item_id)
            ->orderBy('penerimaan.tanggal')
            ->orderBy('penerimaan_detail.id')
            ->lockForUpdate()
            ->select('penerimaan_detail.id as id', 'penerimaan_detail.jumlah as jumlah')
            ->get();

        foreach ($daftarBatch as $batch) {
            if ($dibutuhkan <= 0) {
                break;
            }

            // Unit yang menempel ke surat jalan berstatus dibatalkan sudah
            // kembali ke gudang (mutasi balik IN ikut ditulis di sana) — jadi
            // tidak dihitung sebagai "sudah dipakai" di sini (§3a.4).
            $sudahDipakai = (int) AlokasiBatchKeluar::query()
                ->where('penerimaan_detail_id', $batch->id)
                ->whereHas(
                    'suratJalanDetail.suratJalan',
                    fn ($q) => $q->where('status', '!=', StatusSuratJalan::Dibatalkan->value),
                )
                ->sum('qty_dialokasikan');

            $sisaBatch = (int) $batch->jumlah - $sudahDipakai;

            if ($sisaBatch <= 0) {
                continue;
            }

            $ambil = min($sisaBatch, $dibutuhkan);

            AlokasiBatchKeluar::create([
                'penerimaan_detail_id' => $batch->id,
                'surat_jalan_detail_id' => $suratJalanDetail->id,
                'qty_dialokasikan' => $ambil,
            ]);

            $dibutuhkan -= $ambil;
        }

        if ($dibutuhkan > 0) {
            throw new RuntimeException(
                "Alokasi FIFO gagal buat item #{$suratJalanDetail->item_id}: kurang {$dibutuhkan} unit dari batch yang tersedia. ".
                'Data batch penerimaan kemungkinan tidak sinkron dengan ledger stok_mutasi.'
            );
        }
    }
}
