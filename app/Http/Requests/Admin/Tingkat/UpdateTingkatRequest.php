<?php

namespace App\Http\Requests\Admin\Tingkat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTingkatRequest extends FormRequest
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
            'nama_tingkat' => ['sometimes', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'string'],
            'urutan' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
