<?php

namespace Database\Seeders;

use App\Enums\BasisTarif;
use App\Models\Gudang;
use App\Models\TarifSewa;
use Illuminate\Database\Seeder;

class TarifSewaSeeder extends Seeder
{
    /**
     * Basis final KG_AKTUAL (item.berat_kg sudah terisi, walau masih estimasi —
     * lihat ItemSeeder). Dua harga per "04-invoice-sewa-gudang.md" §0 keputusan
     * #2: harga_jual Rp 4,5rb/kg/hari (tampil di invoice), harga_beli Rp
     * 3,5rb/kg/hari (cost internal, laporan margin, TIDAK tampil di cetak).
     * PPN masih placeholder 11%, belum final dari klien.
     *
     * Ganti tarif = tutup baris ini (isi berlaku_sampai) + tambah baris baru,
     * TIDAK PERNAH mengubah baris lama (CLAUDE.md §5, komentar migration
     * tarif_sewa) — supaya invoice lama tetap bisa direproduksi persis.
     */
    public function run(): void
    {
        $gudang = Gudang::where('kode', 'GDG-SMG')->first();

        if (! $gudang) {
            return;
        }

        TarifSewa::updateOrCreate(
            [
                'gudang_id' => $gudang->id,
                'berlaku_mulai' => '2026-09-01',
            ],
            [
                'basis' => BasisTarif::KgAktual,
                'harga_jual_per_satuan_per_hari' => 4.5,
                'harga_beli_per_satuan_per_hari' => 3.5,
                'faktor_volumetrik_kg_per_m3' => 250,
                'ppn_persen' => 11,
                'min_hari_simpan' => 0,
                'pembulatan_rupiah' => null,
                'berlaku_sampai' => null,
            ]
        );
    }
}
