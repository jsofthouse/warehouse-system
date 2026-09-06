<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class AlokasiUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dicek di middleware route
    }

    public function rules(): array
    {
        return [
            'jumlah' => ['required', 'array'],
            'jumlah.*' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
