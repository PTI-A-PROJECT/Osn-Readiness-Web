<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Email disamakan huruf kecilnya supaya A@x.com dan a@x.com tidak
     * menjadi dua akun. NIM dipangkas spasi tepinya.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nim'))) {
            $this->merge(['nim' => trim($this->input('nim'))]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required_without:nim', 'nullable', 'email'],
            'nim' => ['required_without:email', 'nullable', 'string', 'regex:/^\d{8,20}$/'],
            'password' => ['required', 'string'],
        ];
    }
}
