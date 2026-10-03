<?php

namespace App\Http\Requests\Siswa;

use Illuminate\Foundation\Http\FormRequest;

class PretestStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'tingkat_seleksi_id' => 'required|exists:tingkat_seleksi,id',
        ];
    }
}
