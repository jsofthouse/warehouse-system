<?php

namespace App\Http\Requests\Transaksi;

use App\Models\SuratJalan;
use App\Support\KetersediaanPengiriman;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi header + baris detail surat jalan.
 *
 * Tiga hal yang tidak boleh dilonggarkan ("08-keamanan.md" §3,
 * "10-modul-surat-jalan.md" §5):
 * 1. `gudang_id` untuk operator_gudang SELALU diambil dari user, apa pun yang
 *    dikirim form-nya.
 * 2. `status`, `nomor_surat_jalan`, `posted_*`, `dibatalkan_*`, `tanggal_terima`,
 *    `nama_penerima` tidak ada di sini sama sekali — semuanya diisi controller.
 * 3. Batas jumlah kirim (stok gudang dan sisa alokasi) dihitung ulang di server,
 *    tidak pernah dipercaya dari nilai yang dikirim browser.
 */
class SuratJalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dicek middleware route, dokumen dicek SuratJalanPolicy
    }

    public function rules(): array
    {
        return [
            // Sudah dipaksa ke gudang user di prepareForValidation() untuk role
            // non-lintas gudang; tetap divalidasi supaya gudang nonaktif atau
            // user tanpa penempatan ditolak dengan pesan, bukan error 500.
            'gudang_id' => [
                'required', 'integer',
                Rule::exists('gudangs', 'id')->where('is_active', true),
            ],

            // Satu dokumen = satu lokasi tujuan ("10-modul-surat-jalan.md" §2.1).
            'lokasi_id' => [
                'required', 'integer',
                Rule::exists('lokasis', 'id')->where('is_active', true),
            ],

            // Backdate bebas (dokumen sering telat diinput), tapi tanggal yang
            // belum terjadi ditolak — mutasi OUT memakai tanggal dokumen ini,
            // jadi tanggal masa depan ikut memotong stok hari ini.
            'tanggal' => ['required', 'date', 'before_or_equal:today'],

            // Teks bebas, tidak ada master ekspedisi di Fase 1 (§2.3).
            'ekspedisi' => ['nullable', 'string', 'max:150'],
            'nomor_polisi' => ['nullable', 'string', 'max:20'],
            'nama_sopir' => ['nullable', 'string', 'max:100'],

            // Draft boleh disimpan tanpa baris sama sekali; kelengkapannya baru
            // dipaksa saat posting (lihat SuratJalanController::posting()).
            'detail' => ['sometimes', 'array', 'max:200'],
            'detail.*.item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('items', 'id')->where('is_active', true),
            ],
            'detail.*.jumlah_kirim' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * Batas keras per baris: tidak boleh melebihi stok gudang, dan tidak boleh
     * melebihi sisa alokasi lokasi tujuan ("10-modul-surat-jalan.md" §10.2).
     * Dua-duanya dihitung ulang di server dari ledger + master alokasi.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return; // gudang_id/lokasi_id belum tentu valid, jangan dihitung
            }

            $detail = $this->input('detail', []);

            if (! is_array($detail) || $detail === []) {
                return;
            }

            $ketersediaan = KetersediaanPengiriman::untuk(
                (int) $this->input('gudang_id'),
                (int) $this->input('lokasi_id'),
            );

            foreach ($detail as $baris => $isi) {
                $itemId = (int) ($isi['item_id'] ?? 0);
                $diminta = (int) ($isi['jumlah_kirim'] ?? 0);
                $angka = $ketersediaan->get($itemId);

                if ($angka === null) {
                    continue; // sudah kena rule exists di atas
                }

                $nama = $angka->item->nama;
                $kolom = "detail.{$baris}.jumlah_kirim";

                if ($diminta > $angka->stok_gudang) {
                    $validator->errors()->add($kolom, sprintf(
                        '%s: stok gudang tinggal %d unit, tidak bisa kirim %d.',
                        $nama,
                        $angka->stok_gudang,
                        $diminta,
                    ));

                    continue;
                }

                if ($diminta > $angka->sisa) {
                    $validator->errors()->add($kolom, sprintf(
                        '%s: sisa alokasi lokasi ini tinggal %d unit (alokasi %d, sudah terkirim %d). '
                        .'Kalau lapangan memang perlu kirim lebih, ubah dulu alokasinya di menu Alokasi Kebutuhan.',
                        $nama,
                        $angka->sisa,
                        $angka->alokasi,
                        $angka->sudah_kirim,
                    ));
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gudang_id' => $this->gudangIdDariServer(),
            'detail' => $this->detailBersih(),
        ]);
    }

    /**
     * Gudang tidak pernah diambil mentah dari form kecuali untuk role lintas
     * gudang saat membuat dokumen baru:
     * - edit dokumen: selalu ikut gudang dokumen (dokumen tidak pindah gudang),
     * - operator_gudang/viewer: selalu gudang penempatannya,
     * - super_admin/operator_pusat saat create: boleh memilih, tapi divalidasi
     *   ke daftar gudang yang benar-benar ada dan aktif.
     */
    private function gudangIdDariServer(): mixed
    {
        $suratJalan = $this->route('suratJalan');

        if ($suratJalan instanceof SuratJalan) {
            return $suratJalan->gudang_id;
        }

        $user = $this->user();

        return $user?->bisaAksesSemuaGudang()
            ? $this->input('gudang_id')
            : $user?->gudang_id;
    }

    /**
     * Layar buat surat jalan merender semua item aktif sebagai baris, tapi cuma
     * baris berjumlah > 0 yang ikut terkirim. Baris kosong dibuang dan indeksnya
     * disusun ulang supaya nomor baris di pesan error cocok dengan yang dikirim.
     *
     * @return array<int, array<string, mixed>>
     */
    private function detailBersih(): array
    {
        $detail = $this->input('detail');

        if (! is_array($detail)) {
            return [];
        }

        return array_values(array_filter($detail, function ($baris) {
            if (! is_array($baris)) {
                return false;
            }

            $tanpaItem = ($baris['item_id'] ?? '') === '';
            $tanpaJumlah = ($baris['jumlah_kirim'] ?? '') === ''
                || (int) ($baris['jumlah_kirim'] ?? 0) === 0;

            return ! ($tanpaItem && $tanpaJumlah);
        }));
    }

    public function attributes(): array
    {
        return [
            'gudang_id' => 'gudang',
            'lokasi_id' => 'lokasi tujuan',
            'tanggal' => 'tanggal pengiriman',
            'nomor_polisi' => 'nomor polisi',
            'nama_sopir' => 'nama sopir',
            'detail.*.item_id' => 'item',
            'detail.*.jumlah_kirim' => 'jumlah kirim',
        ];
    }

    public function messages(): array
    {
        return [
            'gudang_id.required' => 'Gudang belum ditentukan. Akun operator gudang wajib punya penempatan gudang.',
            'gudang_id.exists' => 'Gudang tidak ditemukan atau sudah dinonaktifkan.',
            'lokasi_id.required' => 'Pilih dulu lokasi tujuan pengiriman.',
            'lokasi_id.exists' => 'Lokasi tujuan tidak ditemukan atau sudah dinonaktifkan.',
            'tanggal.before_or_equal' => 'Tanggal pengiriman tidak boleh melewati hari ini.',
            'detail.*.item_id.distinct' => 'Item ini sudah dipakai di baris lain. Gabungkan jumlahnya jadi satu baris.',
            'detail.*.item_id.exists' => 'Item tidak ditemukan atau sudah dinonaktifkan.',
            'detail.*.jumlah_kirim.min' => 'Jumlah kirim minimal 1.',
        ];
    }
}
