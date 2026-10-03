<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AturanPemetaanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tingkat_id' => $this->tingkat_id,
            'parameter' => $this->parameter,
            'ketentuan' => $this->ketentuan,
            'bobot_pretest' => $this->bobot_pretest,
            'persen_pretest_mudah' => $this->persen_pretest_mudah,
            'persen_pretest_sedang' => $this->persen_pretest_sedang,
            'persen_pretest_sulit' => $this->persen_pretest_sulit,
            'passing_grade_pretest' => $this->passing_grade_pretest,
            'bobot_simulasi' => $this->bobot_simulasi,
            'persen_simulasi_mudah' => $this->persen_simulasi_mudah,
            'persen_simulasi_sedang' => $this->persen_simulasi_sedang,
            'persen_simulasi_sulit' => $this->persen_simulasi_sulit,
            'passing_grade_simulasi' => $this->passing_grade_simulasi,
            'latihan_min_nilai' => $this->latihan_min_nilai,
            'pretest_jumlah_soal' => $this->pretest_jumlah_soal,
            'pretest_min_soal_per_materi' => $this->pretest_min_soal_per_materi,
            'simulasi_maks_percobaan' => $this->simulasi_maks_percobaan,
            'jumlah_materi_wajib' => $this->jumlah_materi_wajib,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
