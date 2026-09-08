<?php

namespace Database\Seeders;

use App\Models\AlokasiKebutuhan;
use App\Models\Item;
use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class AlokasiKebutuhanLokasiTambahanSeeder extends Seeder
{
    /**
     * Alokasi kebutuhan untuk 18 lokasi tambahan (LOK-33..LOK-50) dari data
     * lokasi part 2 ("lokasi kodim 2.jpeg", 7 September 2026). Dipisah dari
     * `AlokasiKebutuhanSeeder` (yang cakupannya 32 lokasi awal) supaya seeder
     * lama tidak perlu diubah — sengaja tidak looping ke Lokasi::all() lagi.
     *
     * Pakai asumsi SERAGAM yang sama dengan seeder lama: kolom
     * "kebutuhan_per_lokasi" di data/item.csv, 104 unit per lokasi (lihat
     * "Pertanyaan Terbuka ke Klien.md", asumsi #2) — TETAP BISA diubah per
     * lokasi lewat layar Alokasi nanti.
     */
    private const KEBUTUHAN_PER_LOKASI = [
        'ALK-01' => 1,
        'ALK-02' => 2,
        'ALK-03' => 1,
        'ALK-04' => 3,
        'ALK-05' => 6,
        'ALK-06' => 2,
        'ALK-07' => 9,
        'ALK-08' => 9,
        'ALK-09' => 7,
        'ALK-10' => 6,
        'ALK-11' => 7,
        'ALK-12' => 7,
        'ALK-13' => 10,
        'ALK-14' => 10,
        'ALK-15' => 12,
        'ALK-16' => 6,
        'ALK-17' => 6,
    ];

    private const KODE_LOKASI_BARU = [
        'LOK-33', 'LOK-34', 'LOK-35', 'LOK-36', 'LOK-37',
        'LOK-38', 'LOK-39', 'LOK-40', 'LOK-41', 'LOK-42',
        'LOK-43', 'LOK-44', 'LOK-45', 'LOK-46', 'LOK-47',
        'LOK-48', 'LOK-49', 'LOK-50',
    ];

    public function run(): void
    {
        $itemIdsByKode = Item::pluck('id', 'kode');
        $lokasiIds = Lokasi::whereIn('kode', self::KODE_LOKASI_BARU)->pluck('id', 'kode');

        if ($itemIdsByKode->isEmpty()) {
            $this->command?->warn('ItemSeeder belum jalan — skip AlokasiKebutuhanLokasiTambahanSeeder.');

            return;
        }

        if ($lokasiIds->isEmpty()) {
            $this->command?->warn('Lokasi LOK-33..LOK-50 belum ada (jalankan LokasiSeeder dulu) — skip AlokasiKebutuhanLokasiTambahanSeeder.');

            return;
        }

        foreach ($lokasiIds as $kodeLokasi => $lokasiId) {
            foreach (self::KEBUTUHAN_PER_LOKASI as $kode => $jumlah) {
                $itemId = $itemIdsByKode[$kode] ?? null;

                if (! $itemId) {
                    continue;
                }

                AlokasiKebutuhan::updateOrCreate(
                    ['lokasi_id' => $lokasiId, 'item_id' => $itemId],
                    ['jumlah_kebutuhan' => $jumlah]
                );
            }
        }
    }
}
