<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAturanPemetaanRequest extends FormRequest
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
            'parameter' => ['nullable', 'string', 'max:255'],
            'ketentuan' => ['nullable', 'string'],
            'bobot_pretest' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persen_pretest_mudah' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persen_pretest_sedang' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persen_pretest_sulit' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'passing_grade_pretest' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_simulasi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persen_simulasi_mudah' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persen_simulasi_sedang' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persen_simulasi_sulit' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'passing_grade_simulasi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'latihan_min_nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pretest_jumlah_soal' => ['nullable', 'integer', 'min:0'],
            'pretest_min_soal_per_materi' => ['nullable', 'integer', 'min:0'],
            'simulasi_maks_percobaan' => ['nullable', 'integer', 'min:0'],
            'jumlah_materi_wajib' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
