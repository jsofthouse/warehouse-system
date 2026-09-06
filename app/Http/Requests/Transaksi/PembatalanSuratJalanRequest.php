<?php

namespace App\Http\Requests\Transaksi;

use App\Models\SuratJalan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pembatalan surat jalan ter-posting menulis mutasi balik (IN) ke buku besar
 * stok dan tidak bisa dibalik. Alasannya wajib dan harus bisa dibaca orang lain
 * saat audit, jadi bukan sekadar "salah" satu kata.
 *
 * Beda dari Barang Masuk: tidak ada pengecekan konflik stok, karena membalik
 * OUT jadi IN tidak pernah bisa membuat stok negatif
 * ("10-modul-surat-jalan.md" §2.4).
 */
class PembatalanSuratJalanRequest extends FormRequest
{
    /**
     * Hanya dari status `posted`. Dokumen yang sudah `diterima` tidak bisa
     * dibatalkan lagi — barang sudah difisik diterima di lokasi, koreksinya
     * lewat retur/susulan (§2.5). Dicek di sini juga, bukan cuma di
     * SuratJalanPolicy, supaya request pembatalan atas dokumen berstatus salah
     * berhenti sebelum menyentuh controller.
     */
    public function authorize(): bool
    {
        $suratJalan = $this->route('suratJalan');

        return $suratJalan instanceof SuratJalan
            && $suratJalan->status->bisaDibatalkan();
    }

    public function rules(): array
    {
        return [
            'alasan_pembatalan' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'alasan_pembatalan' => 'alasan pembatalan',
        ];
    }

    public function messages(): array
    {
        return [
            'alasan_pembatalan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan_pembatalan.min' => 'Tulis alasan yang jelas, minimal 10 karakter — ini yang dibaca saat audit.',
        ];
    }
}
