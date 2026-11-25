<?php

namespace App\Services;

use App\Contracts\Services\DashboardAdminServiceInterface;
use App\Models\HasilSimulasi;
use App\Models\Pretest;
use App\Models\QuizPengerjaan;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class DashboardAdminService implements DashboardAdminServiceInterface
{
    public function ringkasan(?CarbonInterface $dari = null, ?CarbonInterface $sampai = null): array
    {
        $siswaAktif = User::query()
            ->where('is_active', true)
            ->role('siswa')
            ->count();

        $pengerjaan = [
            'pretest' => Pretest::count(),
            'latihan' => QuizPengerjaan::count(),
            'simulasi' => HasilSimulasi::count(),
        ];

        $perTingkat = TingkatSeleksi::query()
            ->orderBy('urutan')
            ->get()
            ->map(fn (TingkatSeleksi $tingkat): array => [
                'tingkat_id' => (int) $tingkat->id,
                'nama_tingkat' => (string) $tingkat->nama_tingkat,
                'urutan' => (int) $tingkat->urutan,
                'jumlah_siswa' => User::query()
                    ->where('is_active', true)
                    ->where('tingkat_aktif_id', $tingkat->id)
                    ->count(),
            ])
            ->all();

        return [
            'siswa_aktif' => $siswaAktif,
            'pengerjaan_per_jenis' => $pengerjaan,
            'siswa_per_tingkat_aktif' => $perTingkat,
            'rata_rata_nilai_per_jenis' => [
                'pretest' => round((float) Pretest::whereNotNull('nilai')->avg('nilai'), 2),
                'latihan' => round((float) QuizPengerjaan::whereNotNull('nilai')->avg('nilai'), 2),
                'simulasi' => round((float) HasilSimulasi::whereNotNull('nilai')->avg('nilai'), 2),
            ],
            'analitik_pengguna' => $this->analitikPengguna($dari, $sampai),
        ];
    }

    /**
     * Angka analitik pengguna. Tanpa filter: seluruh waktu. Dengan filter, tiap
     * metrik memakai kolom waktunya sendiri dengan batas inklusif per hari:
     * pendaftaran dan referal memakai users.created_at, mulai memakai created_at
     * (pretest, quiz_pengerjaan) atau mulai_pada (hasil_simulasi), dan selesai
     * memakai disubmit_pada. Kategori boleh tumpang tindih.
     *
     * @return array{siswa_terdaftar: int, mulai_pretest: int, mulai_latihan: int, mulai_simulasi: int, selesai_pretest: int, selesai_latihan: int, selesai_simulasi: int, terdaftar_dengan_referal: int}
     */
    private function analitikPengguna(?CarbonInterface $dari, ?CarbonInterface $sampai): array
    {
        $dari = $dari?->copy()->startOfDay();
        $sampai = $sampai?->copy()->endOfDay();

        $siswa = $this->dalamPeriode(User::role('siswa'), 'users.created_at', $dari, $sampai)
            ->selectRaw('count(*) as jumlah, count(direferensikan_oleh) as referal')
            ->first();

        return [
            'siswa_terdaftar' => (int) $siswa->jumlah,
            'mulai_pretest' => $this->penggunaUnik('pretest', 'created_at', $dari, $sampai),
            'mulai_latihan' => $this->penggunaUnik('quiz_pengerjaan', 'created_at', $dari, $sampai),
            'mulai_simulasi' => $this->penggunaUnik('hasil_simulasi', 'mulai_pada', $dari, $sampai),
            'selesai_pretest' => $this->penggunaUnik('pretest', 'disubmit_pada', $dari, $sampai),
            'selesai_latihan' => $this->penggunaUnik('quiz_pengerjaan', 'disubmit_pada', $dari, $sampai),
            'selesai_simulasi' => $this->penggunaUnik('hasil_simulasi', 'disubmit_pada', $dari, $sampai),
            'terdaftar_dengan_referal' => (int) $siswa->referal,
        ];
    }

    private function penggunaUnik(string $tabel, string $kolomWaktu, ?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $query = DB::table($tabel)
            ->whereNotNull("$tabel.$kolomWaktu")
            ->whereIn("$tabel.user_id", User::role('siswa')->select('users.id'));

        return $this->dalamPeriode($query, "$tabel.$kolomWaktu", $dari, $sampai)
            ->distinct()
            ->count("$tabel.user_id");
    }

    private function dalamPeriode(Builder|QueryBuilder $query, string $kolom, ?CarbonInterface $dari, ?CarbonInterface $sampai): Builder|QueryBuilder
    {
        return $query
            ->when($dari !== null, fn ($q) => $q->where($kolom, '>=', $dari))
            ->when($sampai !== null, fn ($q) => $q->where($kolom, '<=', $sampai));
    }
}
