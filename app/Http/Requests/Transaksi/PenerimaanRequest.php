<?php

namespace App\Http\Requests\Transaksi;

use App\Models\Penerimaan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi header + baris detail penerimaan.
 *
 * Dua hal yang tidak boleh dilonggarkan ("08-keamanan.md" §3):
 * 1. `gudang_id` untuk operator_gudang SELALU diambil dari user, apa pun yang
 *    dikirim form-nya.
 * 2. `status`, `nomor_penerimaan`, `posted_*`, `dibatalkan_*` tidak ada di sini
 *    sama sekali — semuanya diisi controller.
 */
class PenerimaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dicek middleware route, dokumen dicek PenerimaanPolicy
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

            // Backdate bebas (dokumen sering telat diinput), tapi tanggal yang
            // belum terjadi ditolak — "03-aturan-bisnis.md" §3.6.
            'tanggal' => ['required', 'date', 'before_or_equal:today'],

            'vendor_nama' => ['required', 'string', 'max:255'],
            'nomor_dokumen_vendor' => ['nullable', 'string', 'max:255'],
            'no_kontrak_referensi' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:2000'],

            // Draft boleh disimpan tanpa baris sama sekali; kelengkapannya baru
            // dipaksa saat posting (lihat PenerimaanController::posting()).
            'detail' => ['sometimes', 'array', 'max:200'],
            'detail.*.item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('items', 'id')->where('is_active', true),
            ],
            'detail.*.jumlah' => ['required', 'integer', 'min:1', 'max:1000000'],
            'detail.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
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
        $penerimaan = $this->route('penerimaan');

        if ($penerimaan instanceof Penerimaan) {
            return $penerimaan->gudang_id;
        }

        $user = $this->user();

        return $user?->bisaAksesSemuaGudang()
            ? $this->input('gudang_id')
            : $user?->gudang_id;
    }

    /**
     * Buang baris yang benar-benar kosong (operator menambah baris lalu tidak
     * mengisinya) dan susun ulang indeksnya supaya pesan error nomor barisnya
     * cocok dengan yang dilihat operator di layar.
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

            $kosong = ($baris['item_id'] ?? '') === '' && ($baris['jumlah'] ?? '') === '';

            return ! $kosong;
        }));
    }

    public function attributes(): array
    {
        return [
            'gudang_id' => 'gudang',
            'tanggal' => 'tanggal penerimaan',
            'vendor_nama' => 'nama vendor',
            'nomor_dokumen_vendor' => 'nomor dokumen vendor',
            'no_kontrak_referensi' => 'nomor kontrak/referensi',
            'detail.*.item_id' => 'item',
            'detail.*.jumlah' => 'jumlah',
        ];
    }

    public function messages(): array
    {
        return [
            'gudang_id.required' => 'Gudang belum ditentukan. Akun operator gudang wajib punya penempatan gudang.',
            'gudang_id.exists' => 'Gudang tidak ditemukan atau sudah dinonaktifkan.',
            'tanggal.before_or_equal' => 'Tanggal penerimaan tidak boleh melewati hari ini.',
            'detail.*.item_id.distinct' => 'Item ini sudah dipakai di baris lain. Gabungkan jumlahnya jadi satu baris.',
            'detail.*.item_id.exists' => 'Item tidak ditemukan atau sudah dinonaktifkan.',
            'detail.*.jumlah.min' => 'Jumlah minimal 1.',
        ];
    }
}
