<?php

namespace App\Http\Requests\Admin\Kompetensi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreKompetensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tingkat_id' => ['required', 'integer', 'exists:tingkat_seleksi,id'],
            'nama_kompetensi' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
        ];
    }
}
