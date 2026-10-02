<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TingkatSeleksiStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'urutan' => 'required|integer|min:1',
            'kode_propinsi' => 'nullable|string|max:10',
        ];
    }
}
