<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * 17 item Alkap Pertanian, diekstrak dari `product list.jpeg` klien
     * (lihat project doc data/item.csv & data/README.md).
     *
     * Catatan penting yang diwarisi dari sumbernya:
     * - berat_kg SENGAJA null buat semua item — data berat belum diberikan klien,
     *   syarat mutlak modul invoice sewa gudang (lihat "Modul Invoice Sewa
     *   Gudang.md" §1). JANGAN diisi angka karangan.
     * - 6 item tanpa dimensi (ALK-01, 03, 06, 11, 13, 14) — dibiarkan null.
     * - Kode ALK-01..17 dibuat sendiri karena klien belum kasih penomoran resmi.
     *   Ganti kalau klien punya kode sendiri.
     *
     * volume_m3 dihitung dari dimensi (p × l × t / 1.000.000), bukan diketik manual,
     * biar konsisten kalau dimensi dikoreksi belakangan.
     */
    private const ITEMS = [
        // kode, nama, satuan, panjang_cm, lebar_cm, tinggi_cm
        ['ALK-01', 'RAN TRAKTOR', 'UNIT', null, null, null],
        ['ALK-02', 'TRAKTOR TANGAN', 'UNIT', 130, 80, 80],
        ['ALK-03', 'ROTAVATOR', 'UNIT', null, null, null],
        ['ALK-04', 'CULTIVATOR', 'UNIT', 113, 45, 87],
        ['ALK-05', 'BAJAK SINGKAL', 'UNIT', 76, 28, 74],
        ['ALK-06', 'GARU PIRING', 'UNIT', null, null, null],
        ['ALK-07', 'MESIN PENANAM BENIH', 'UNIT', 214, 159, 91],
        ['ALK-08', 'MESIN PENANAM JAGUNG', 'UNIT', 95, 120, 30],
        ['ALK-09', 'MESIN PENANAM SAYUR', 'UNIT', 95, 120, 30],
        ['ALK-10', 'MESIN PERONTOK PADI', 'UNIT', 72, 54, 62],
        ['ALK-11', 'MESIN PERONTOK JAGUNG', 'UNIT', null, null, null],
        ['ALK-12', 'MESIN PENGGILING PADI', 'UNIT', 150, 40, 116],
        ['ALK-13', 'MESIN POMPA AIR', 'UNIT', null, null, null],
        ['ALK-14', 'MESIN PENCACAH', 'UNIT', null, null, null],
        ['ALK-15', 'MIST BLOWER PEMBASMI HAMA', 'UNIT', 50, 42, 72],
        ['ALK-16', 'GENSET', 'UNIT', 95, 55, 75],
        ['ALK-17', 'FLEXY TANK CLOSE 500 & 1000 LTR', 'UNIT', 45, 40, 30],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as [$kode, $nama, $satuan, $p, $l, $t]) {
            $volumeM3 = ($p && $l && $t)
                ? round(($p * $l * $t) / 1_000_000, 4)
                : null;

            Item::updateOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $nama,
                    'satuan' => $satuan,
                    'berat_kg' => null,
                    'panjang_cm' => $p,
                    'lebar_cm' => $l,
                    'tinggi_cm' => $t,
                    'volume_m3' => $volumeM3,
                    'is_active' => true,
                ]
            );
        }
    }
}
