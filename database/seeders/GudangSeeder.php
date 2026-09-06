<?php

namespace Database\Seeders;

use App\Models\Gudang;
use Illuminate\Database\Seeder;

class GudangSeeder extends Seeder
{
    /**
     * Fase 1 cuma satu gudang: Semarang.
     * Lihat "Spesifikasi Sistem Gudang Alkap.md" §5.1 & §9.
     */
    public function run(): void
    {
        Gudang::updateOrCreate(
            ['kode' => 'GDG-SMG'],
            [
                'nama' => 'Gudang Semarang',
                'kota' => 'Semarang',
                'alamat' => null,
                'is_active' => true,
            ]
        );
    }
}
