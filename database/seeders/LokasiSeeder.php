<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class LokasiSeeder extends Seeder
{
    /**
     * 50 lokasi tujuan (desa binaan Kodim), diekstrak dari `lokasi kodim.jpeg`
     * (baris 1-32) dan `lokasi kodim 2.jpeg` (baris 33-50) klien — Jo konfirmasi
     * 7 September 2026 daftar sudah lengkap, total 50 titik (lihat project doc
     * data/lokasi.csv & data/README.md).
     *
     * Catatan penting yang diwarisi dari sumbernya — JANGAN dianggap final:
     * - Transkripsi dari gambar beresolusi terbatas, perlu diverifikasi ke berkas
     *   Excel asli sebelum dipakai produksi. Yang paling perlu dicek: baris 5
     *   (Sukabumi, "Kec. Waluran Kiara") dan baris 39 (Lumajang, desa tertulis
     *   "Krajan Dua, Burno" — kemungkinan dusun + desa, bukan dua desa).
     * - `nama_kodim` di sini masih placeholder "Kodim {kabupaten}" — kode satuan
     *   Kodim yang resmi belum didapat dari klien, JANGAN dikarang jadi nomor
     *   satuan (mis. "Kodim 0618/BS") tanpa konfirmasi.
     * - Kode LOK-01..50 dibuat sendiri, ganti kalau klien punya penomoran sendiri.
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
        ['Jawa Tengah', 'Blora', 'Kunduran', 'Kedungwaru'],
        ['Jawa Timur', 'Banyuwangi', 'Purwoharjo', 'Glagahagung'],
        ['Jawa Timur', 'Bondowoso', 'Sumberwringin', 'Sukorejo'],
        ['Jawa Timur', 'Gresik', 'Panceng', 'Wotan'],
        ['Jawa Timur', 'Jombang', 'Mojowarno', 'Grobogan'],
        ['Jawa Timur', 'Kediri', 'Kandangan', 'Banaran'],
        ['Jawa Timur', 'Lumajang', 'Senduro', 'Krajan Dua, Burno'],
        ['Jawa Timur', 'Madiun', 'Mejayan', 'Kebonagung'],
        ['Jawa Timur', 'Magetan', 'Parang', 'Mategal'],
        ['Jawa Timur', 'Malang', 'Lawang', 'Widodadi'],
        ['Jawa Timur', 'Mojokerto', 'Mojoanyar', 'Kepuhanyar'],
        ['Jawa Timur', 'Ngawi', 'Widodaren', 'Sidolaju'],
        ['Jawa Timur', 'Pamekasan', 'Tlanakan', 'Larangan Slampar'],
        ['Jawa Timur', 'Ponorogo', 'Pulung', 'Pulung'],
        ['Jawa Timur', 'Blitar', 'Kasamben', 'Jugo'],
        ['Jawa Timur', 'Situbondo', 'Kendit', 'Klatakan'],
        ['Jawa Timur', 'Trenggalek', 'Watulimo', 'Karanggandu'],
        ['Jawa Timur', 'Jember', 'Silo', 'Silo'],
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
