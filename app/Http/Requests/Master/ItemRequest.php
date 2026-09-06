<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dicek di middleware route
    }

    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'kode' => ['required', 'string', 'max:30', Rule::unique('items', 'kode')->ignore($itemId)],
            'nama' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:20'],
            'berat_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999.999'],
            'panjang_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'lebar_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'tinggi_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
