<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class LokasiSeeder extends Seeder
{
    /**
     * 32 lokasi tujuan (desa binaan Kodim), diekstrak dari `lokasi kodim.jpeg`
     * klien (lihat project doc data/lokasi.csv & data/README.md).
     *
     * Catatan penting yang diwarisi dari sumbernya — JANGAN dianggap final:
     * - Daftar ini KEMUNGKINAN belum lengkap — gambar sumber terpotong di baris 32.
     * - Transkripsi dari gambar beresolusi terbatas, perlu diverifikasi ke berkas
     *   Excel asli sebelum dipakai produksi. Yang paling perlu dicek: baris 5
     *   (Sukabumi, "Kec. Waluran Kiara").
     * - `nama_kodim` di sini masih placeholder "Kodim {kabupaten}" — kode satuan
     *   Kodim yang resmi belum didapat dari klien, JANGAN dikarang jadi nomor
     *   satuan (mis. "Kodim 0618/BS") tanpa konfirmasi.
     * - Kode LOK-01..32 dibuat sendiri, ganti kalau klien punya penomoran sendiri.
     */
    private const LOKASI = [
        // provinsi, kabupaten, kecamatan, desa
        ['Jawa Barat', 'Bandung', 'Kertasari', 'Cikembang'],
        ['Jawa Barat', 'Cirebon', 'Pasaleman', 'Tonjong'],
        ['Jawa Barat', 'Majalengka', 'Kertajati', 'Sahbandar'],
        ['Jawa Barat', 'Pangandaran', 'Cimerak', 'Limusgede'],
        ['Jawa Barat', 'Sukabumi', 'Waluran Kiara', 'Ubrug'],
        ['Jawa Barat', 'Sumedang', 'Ujungjaya', 'Ujungjaya'],
        ['Jawa Barat', 'Cianjur', 'Sukanagara', 'Sukalaksana'],
        ['Jawa Barat', 'Tasikmalaya', 'Cipatujah', 'Ciandum'],
        ['Jawa Barat', 'Purwakarta', 'Wanayasa', 'Taringgul Tengah'],
        ['Jawa Tengah', 'Banjarnegara', 'Punggelan', 'Sidarata'],
        ['Jawa Tengah', 'Banyumas', 'Patikraja', 'Sawangan Wetan'],
        ['Jawa Tengah', 'Batang', 'Wonotunggal', 'Sigayam'],
        ['Jawa Tengah', 'Boyolali', 'Juwangi', 'Pilangrejo'],
        ['Jawa Tengah', 'Grobogan', 'Kedungjati', 'Kalimaro'],
        ['Jawa Tengah', 'Jepara', 'Bangsri', 'Jerukwangi'],
        ['Jawa Tengah', 'Karanganyar', 'Kerjo', 'Sumberejo'],
        ['Jawa Tengah', 'Kebumen', 'Buayan', 'Banyumudal'],
        ['Jawa Tengah', 'Klaten', 'Bayat', 'Krakitan'],
        ['Jawa Tengah', 'Magelang', 'Windusari', 'Wonoroto'],
        ['Jawa Tengah', 'Pekalongan', 'Bojong', 'Bukur'],
        ['Jawa Tengah', 'Pemalang', 'Bantarbolang', 'Karanganyar'],
        ['Jawa Tengah', 'Purbalingga', 'Karangreja', 'Serang'],
        ['Jawa Tengah', 'Purworejo', 'Gebang', 'Pelutan'],
        ['Jawa Tengah', 'Semarang', 'Bancak', 'Plumutan'],
        ['Jawa Tengah', 'Sragen', 'Tangen', 'Katelan'],
        ['Jawa Tengah', 'Sukoharjo', 'Polokarto', 'Tepisari'],
        ['Jawa Tengah', 'Tegal', 'Suradadi', 'Harjasari'],
        ['Jawa Tengah', 'Temanggung', 'Bejen', 'Selosabrang'],
        ['Jawa Tengah', 'Wonogiri', 'Nguntoronadi', 'Pondoksari'],
        ['Jawa Tengah', 'Kudus', 'Jekulo', 'Gondoharum'],
        ['Jawa Tengah', 'Wonosobo', 'Kalibawang', 'Kalikarung'],
        ['Jawa Tengah', 'Brebes', 'Banjarharjo', 'Dukuhjeruk'],
    ];

    public function run(): void
    {
        foreach (self::LOKASI as $i => [$provinsi, $kabupaten, $kecamatan, $desa]) {
            $kode = sprintf('LOK-%02d', $i + 1);

            Lokasi::updateOrCreate(
                ['kode' => $kode],
                [
                    'nama_kodim' => "Kodim {$kabupaten}",
                    'provinsi' => $provinsi,
                    'kabupaten' => $kabupaten,
                    'kecamatan' => $kecamatan,
                    'desa' => $desa,
                    'alamat' => "Desa {$desa}, Kec. {$kecamatan}, Kabupaten {$kabupaten}",
                    'is_active' => true,
                ]
            );
        }
    }
}
