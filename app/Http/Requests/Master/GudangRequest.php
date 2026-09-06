<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GudangRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role sudah dicek di middleware route (role:super_admin,operator_pusat).
        return true;
    }

    public function rules(): array
    {
        $gudangId = $this->route('gudang')?->id;

        return [
            'kode' => ['required', 'string', 'max:20', Rule::unique('gudangs', 'kode')->ignore($gudangId)],
            'nama' => ['required', 'string', 'max:255'],
            'kota' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
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
