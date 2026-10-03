<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLatihanRequest extends FormRequest
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
            'nama_quiz' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'jumlah_soal' => ['required', 'integer', 'min:1'],
        ];
    }
}
