<?php

namespace App\Http\Requests\Admin\Dashboard;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RingkasanDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['dari', 'sampai']) || ! $this->filled('dari') || ! $this->filled('sampai')) {
                return;
            }

            // Format YYYY-MM-DD yang sudah lolos validasi bisa dibandingkan sebagai teks.
            if ($this->input('sampai') < $this->input('dari')) {
                $validator->errors()->add('sampai', 'Tanggal sampai harus sama dengan atau setelah tanggal dari.');
            }
        });
    }

    public function dari(): ?Carbon
    {
        return $this->tanggal('dari');
    }

    public function sampai(): ?Carbon
    {
        return $this->tanggal('sampai');
    }

    private function tanggal(string $kolom): ?Carbon
    {
        return $this->filled($kolom) ? Carbon::createFromFormat('!Y-m-d', $this->input($kolom)) : null;
    }
}
