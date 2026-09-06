<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class LokasiImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dicek di middleware route
    }

    public function rules(): array
    {
        return [
            // Cuma CSV — belum ada akses jaringan buat pasang maatwebsite/excel
            // (baca catatan di LokasiController@import). Batas 2 MB cukup untuk
            // ratusan baris lokasi.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
