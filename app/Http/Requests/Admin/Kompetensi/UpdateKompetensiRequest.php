<?php

namespace App\Http\Requests\Admin\Kompetensi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKompetensiRequest extends FormRequest
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
            'tingkat_id' => ['sometimes', 'integer', 'exists:tingkat_seleksi,id'],
            'nama_kompetensi' => ['sometimes', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'string'],
        ];
    }
}
