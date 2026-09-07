<?php

namespace Database\Seeders;

use App\Enums\BasisTarif;
use App\Models\Gudang;
use App\Models\TarifSewa;
use Illuminate\Database\Seeder;

class TarifSewaSeeder extends Seeder
{
    /**
     * Tarif sementara (belum final dari klien) — Rp 5.000/kg/hari, basis
     * KG_VOLUMETRIK karena data berat aktual per item belum ada, PPN placeholder
     * 11%. Lihat "04-invoice-sewa-gudang.md" §0 keputusan #1 & #2, dan
     * "06-pertanyaan-klien.md" A2.
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
                'basis' => BasisTarif::KgVolumetrik,
                'tarif_per_satuan_per_hari' => 5000,
                'faktor_volumetrik_kg_per_m3' => 250,
                'ppn_persen' => 11,
                'min_hari_simpan' => 0,
                'pembulatan_rupiah' => null,
                'berlaku_sampai' => null,
            ]
        );
    }
}
