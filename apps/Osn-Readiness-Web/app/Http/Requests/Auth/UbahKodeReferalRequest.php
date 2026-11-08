<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UbahKodeReferalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => trim($this->input('nama'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,16}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.regex' => 'Nama hanya boleh huruf dan angka tanpa spasi, 2 sampai 16 karakter.',
        ];
    }
}
