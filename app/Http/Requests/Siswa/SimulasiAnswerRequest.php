<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Foundation\Http\FormRequest;

class SimulasiAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'soal_id' => 'required|exists:soal,id',
            'jawaban' => 'nullable|in:a,b,c,d,e',
        ];
    }
}
