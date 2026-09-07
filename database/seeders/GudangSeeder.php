<?php

namespace Database\Seeders;

use App\Models\Gudang;
use Illuminate\Database\Seeder;

class GudangSeeder extends Seeder
{
    /**
     * Fase 1 cuma satu gudang: Semarang — gudang gabungan (joint warehouse)
     * dua brand, JASCO & PrimaPro. Nama dan alamat data asli dari klien
     * (6 September 2026). Lihat "Spesifikasi Sistem Gudang Alkap.md" §5.1 & §8.4.
     *
     * Logo JASCO & PrimaPro untuk kop dokumen cetak menyusul dari klien —
     * belum ada kolom penyimpanannya di master Gudang, sengaja ditunda.
     *
     * nama_penagih = "Primapro Logistik" (7 September 2026, lihat
     * "04-invoice-sewa-gudang.md" §0 keputusan #7) — pihak yang tampil sebagai
     * penerbit di kop invoice. Alamat & NPWP penagih masih null, menunggu data
     * resmi klien ("06-pertanyaan-klien.md" B8).
     */
    public function run(): void
    {
        Gudang::updateOrCreate(
            ['kode' => 'GDG-SMG'],
            [
                'nama' => 'Joint Warehouse JASCO & PrimaPro',
                'kota' => 'Semarang',
                'alamat' => 'Pergudangan Baruna Selatan No.10, TanahMas, Tanjung Emas, Semarang',
                'is_active' => true,
                'nama_penagih' => 'Primapro Logistik',
            ]
        );
    }
}
