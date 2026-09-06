<?php

namespace Database\Seeders;

use App\Models\AlokasiKebutuhan;
use App\Models\Item;
use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class AlokasiKebutuhanSeeder extends Seeder
{
    /**
     * Alokasi kebutuhan SERAGAM di semua lokasi, mengikuti kolom
     * "kebutuhan_per_lokasi" di data/item.csv — ini asumsi sementara klien
     * (lihat "Pertanyaan Terbuka ke Klien.md", asumsi #2), TETAP BISA diubah
     * per lokasi lewat layar Alokasi nanti. Total per lokasi: 104 unit.
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

    public function run(): void
    {
        $itemIdsByKode = Item::pluck('id', 'kode');
        $lokasiIds = Lokasi::pluck('id');

        if ($itemIdsByKode->isEmpty() || $lokasiIds->isEmpty()) {
            $this->command?->warn('ItemSeeder / LokasiSeeder belum jalan — skip AlokasiKebutuhanSeeder.');

            return;
        }

        foreach ($lokasiIds as $lokasiId) {
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
