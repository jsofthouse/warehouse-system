<?php

namespace App\Http\Requests\Pengguna;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dicek di middleware route (role:super_admin)
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'gudang_id' => [
                Rule::requiredIf($this->input('role') === UserRole::OperatorGudang->value),
                'nullable',
                'exists:gudang,id',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            // super_admin & operator_pusat akses lintas gudang — gudang_id dipaksa
            // null di server biar nggak ada sisa pilihan dropdown lama yang bikin
            // scoping parsial. Lihat komentar di migration add_role_and_gudang_id.
            'gudang_id' => in_array($this->input('role'), [
                UserRole::SuperAdmin->value,
                UserRole::OperatorPusat->value,
            ], true) ? null : $this->input('gudang_id'),
        ]);
    }
}
