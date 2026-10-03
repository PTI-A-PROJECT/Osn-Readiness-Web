<?php

namespace App\Http\Requests\Admin\Materi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMateriRequest extends FormRequest
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
            'kompetensi_id' => ['sometimes', 'integer', 'exists:kompetensi,id'],
            'urutan' => ['sometimes', 'integer', 'min:1'],
            'judul' => ['sometimes', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'string'],
            'isi_materi' => ['sometimes', 'string'],
            'id_sumber' => ['nullable', 'string', 'max:255'],
            'file_materi' => ['nullable', 'url'],
        ];
    }
}
