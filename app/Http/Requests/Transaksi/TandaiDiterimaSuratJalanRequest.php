<?php

namespace App\Http\Requests\Transaksi;

use App\Models\SuratJalan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form kecil "Tandai Diterima" ("10-modul-surat-jalan.md" §4.5).
 *
 * `nama_penerima` wajib walaupun kolomnya nullable di DB: barang disalurkan ke
 * instansi negara, jadi siapa yang menerima harus tercatat di sistem, bukan
 * cuma tanda tangan di kertas yang ikut sopir (§10.7).
 *
 * `nomor_bast` opsional — BAST resmi baru dipakai di Fase 3 (§8).
 */
class TandaiDiterimaSuratJalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dicek middleware route, dokumen dicek SuratJalanPolicy
    }

    public function rules(): array
    {
        $suratJalan = $this->route('suratJalan');

        return [
            // Barang tidak mungkin diterima sebelum dikirim, dan tanggal terima
            // di masa depan berarti salah ketik.
            'tanggal_terima' => [
                'required', 'date', 'before_or_equal:today',
                ...($suratJalan instanceof SuratJalan && $suratJalan->tanggal
                    ? ['after_or_equal:'.$suratJalan->tanggal->toDateString()]
                    : []),
            ],
            'nama_penerima' => ['required', 'string', 'max:100'],
            'nomor_bast' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tanggal_terima' => 'tanggal terima',
            'nama_penerima' => 'nama penerima',
            'nomor_bast' => 'nomor BAST',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_terima.required' => 'Tanggal terima wajib diisi.',
            'tanggal_terima.before_or_equal' => 'Tanggal terima tidak boleh melewati hari ini.',
            'tanggal_terima.after_or_equal' => 'Tanggal terima tidak boleh mendahului tanggal pengiriman.',
            'nama_penerima.required' => 'Nama penerima di lokasi wajib diisi — ini yang tercatat di sistem.',
        ];
    }
}
