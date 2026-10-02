<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Foundation\Http\FormRequest;

class PretestSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|exists:soal,id',
            'answers.*.jawaban' => 'nullable|in:a,b,c,d,e',
        ];
    }
}
