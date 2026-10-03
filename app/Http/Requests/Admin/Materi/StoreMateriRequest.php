<?php

namespace App\Http\Requests\Admin\Materi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMateriRequest extends FormRequest
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
            'kompetensi_id' => ['required', 'integer', 'exists:kompetensi,id'],
            'urutan' => ['required', 'integer', 'min:1'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'isi_materi' => ['required', 'string'],
            'id_sumber' => ['nullable', 'string', 'max:255'],
            'file_materi' => ['nullable', 'url'],
        ];
    }
}
