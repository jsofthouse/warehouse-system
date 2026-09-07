<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Buat invoice dari satu surat jalan ("04-invoice-sewa-gudang.md" §3a.5).
 * Pihak tertagih diisi bebas manual (snapshot, bukan pilih dari master — §10).
 */
class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dicek di middleware route
    }

    public function rules(): array
    {
        return [
            'surat_jalan_id' => ['required', 'integer', 'exists:surat_jalan,id'],
            'nama_tertagih' => ['nullable', 'string', 'max:255'],
            'alamat_tertagih' => ['nullable', 'string', 'max:2000'],
            'npwp_tertagih' => ['nullable', 'string', 'max:30'],
        ];
    }
}
