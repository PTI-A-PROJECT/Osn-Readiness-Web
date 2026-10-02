<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TingkatSeleksiUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'nama' => 'sometimes|string|max:255',
            'urutan' => 'sometimes|integer|min:1',
            'kode_propinsi' => 'nullable|string|max:10',
        ];
    }
}
