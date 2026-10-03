<?php

namespace App\Http\Requests;

use App\Enums\Level;
use App\Enums\Peruntukan;
use App\Enums\TipeSoal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSoalRequest extends FormRequest
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
            'id_sumber' => ['nullable', 'string', 'max:255'],
            'tingkat_id' => ['required', 'integer', 'exists:tingkat_seleksi,id'],
            'materi_id' => ['required', 'integer', 'exists:materi,id'],
            'konteks_id' => ['nullable', 'integer', 'exists:konteks_soal,id'],
            'level' => ['required', Rule::enum(Level::class)],
            'peruntukan' => ['required', Rule::enum(Peruntukan::class)],
            'tipe_soal' => ['required', Rule::enum(TipeSoal::class)],
            'pertanyaan' => ['required', 'string'],
            'pilihan_jawaban' => ['required', 'array', 'min:2'],
            'pilihan_jawaban.*' => ['required', 'string'],
            'kunci_jawaban' => ['required', 'integer', 'min:0'],
            'gambar' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
