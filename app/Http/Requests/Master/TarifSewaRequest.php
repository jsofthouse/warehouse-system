<?php

namespace App\Http\Requests\Master;

use App\Enums\BasisTarif;
use App\Models\TarifSewa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TarifSewaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dicek di middleware route
    }

    public function rules(): array
    {
        return [
            'gudang_id' => ['required', 'exists:gudang,id'],
            'basis' => ['required', Rule::enum(BasisTarif::class)],
            'tarif_per_satuan_per_hari' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'faktor_volumetrik_kg_per_m3' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'ppn_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_hari_simpan' => ['nullable', 'integer', 'min:0'],
            'pembulatan_rupiah' => ['nullable', 'integer', 'min:1'],
            'berlaku_mulai' => ['required', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
        ];
    }

    /**
     * Satu gudang cuma boleh punya satu tarif aktif per tanggal — kalau dua
     * tarif tumpang tindih, TarifSewa::scopeBerlakuPada() jadi ambigu dan
     * rumus invoice di "04-invoice-sewa-gudang.md" bisa salah pilih tarif.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $gudangId = $this->input('gudang_id');
            $mulai = $this->input('berlaku_mulai');
            $sampai = $this->input('berlaku_sampai');

            if (! $gudangId || ! $mulai) {
                return;
            }

            $tarifId = $this->route('tarif')?->id;

            $bentrok = TarifSewa::where('gudang_id', $gudangId)
                ->when($tarifId, fn ($q) => $q->where('id', '!=', $tarifId))
                ->where('berlaku_mulai', '<=', $sampai ?: '9999-12-31')
                ->where(function ($q) use ($mulai) {
                    $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $mulai);
                })
                ->exists();

            if ($bentrok) {
                $validator->errors()->add(
                    'berlaku_mulai',
                    'Periode ini tumpang tindih dengan tarif lain yang sudah ada untuk gudang ini. Tutup dulu tarif lama (isi Berlaku Sampai) sebelum menambah yang baru.'
                );
            }
        });
    }
}
