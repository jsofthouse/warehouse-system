<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pembatalan dokumen ter-posting itu aksi yang tidak bisa dibalik dan menulis
 * mutasi balik ke buku besar stok. Alasannya wajib dan harus bisa dibaca orang
 * lain saat audit, jadi bukan sekadar "salah" satu kata.
 */
class PembatalanPenerimaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dicek middleware route, dokumen dicek PenerimaanPolicy
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
