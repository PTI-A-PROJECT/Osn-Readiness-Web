<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aturan:
 *  - Syarat masuk : nilai pre-test terbaik pada tingkat itu >= batas (75 / 85).
 *  - Skor         : jumlah nilai latihan (quiz) terbaik, satu nilai per quiz.
 *  - Urutan       : skor terbesar, lalu total durasi tercepat, lalu selesai paling awal.
 *  - Tidak dihitung: akun admin, akun nonaktif, akun yang sudah dihapus.
 *
 * Latihan = tabel quiz_pengerjaan. Tingkat sebuah quiz diambil dari materi.tingkat_id.
 * Durasi = waktu submit dikurangi created_at pengerjaan (detik).
 *
 * Hasil per tingkat di-cache 30 detik: satu CTE penuh dipakai bersama oleh
 * daftar top dan posisi saya, sehingga halaman leaderboard tidak menghitung
 * ulang peringkat untuk setiap pemanggilan. Naikkan VERSI_CACHE bila bentuk
 * data cache berubah.
 */
class LeaderboardService
{
    private const VERSI_CACHE = 'v1';

    private const TTL_CACHE_DETIK = 30;

    public function tingkatId(string $tingkat): int
    {
        $kata = mb_strtolower((string) config("leaderboard.nama_tingkat.{$tingkat}"));

        $id = DB::table('tingkat_seleksi')
            ->whereRaw('LOWER(nama_tingkat) LIKE ?', ['%'.$kata.'%'])
            ->value('id');

        abort_if($id === null, 404, "Tingkat '{$tingkat}' tidak ditemukan di tabel tingkat_seleksi.");

        return (int) $id;
    }

    public function top(string $tingkat, ?int $limit = null): array
    {
        $limit = max(1, (int) ($limit ?? config('leaderboard.limit')));
        $data = $this->data($tingkat);

        return [
            'tingkat' => $tingkat,
            'batas_pretest' => $data['batas_pretest'],
            'total_peserta' => $data['total_peserta'],
            'peringkat' => array_slice($data['peringkat'], 0, $limit),
        ];
    }

    public function posisiSaya(string $tingkat, int $userId): array
    {
        $tingkatId = $this->tingkatId($tingkat);
        $batas = $this->batas($tingkat);

        $nilaiPretest = DB::table('pretest')
            ->where('user_id', $userId)
            ->where('tingkat_id', $tingkatId)
            ->whereNotNull('nilai')
            ->max('nilai');

        if ($nilaiPretest === null) {
            return [
                'layak' => false,
                'alasan' => "Kerjakan pre-test {$tingkat} dulu. Butuh nilai minimal {$batas} untuk masuk leaderboard.",
                'nilai_pretest' => null,
                'batas_pretest' => $batas,
            ];
        }

        if ($nilaiPretest < $batas) {
            return [
                'layak' => false,
                'alasan' => 'Nilai pre-test '.$this->angka($nilaiPretest).", butuh minimal {$batas} untuk masuk leaderboard {$tingkat}.",
                'nilai_pretest' => $this->angka($nilaiPretest),
                'batas_pretest' => $batas,
            ];
        }

        $data = $this->data($tingkat, $tingkatId);
        $baris = $data['per_pengguna'][$userId] ?? null;

        if ($baris === null) {
            return [
                'layak' => true,
                'peringkat' => null,
                'alasan' => 'Kerjakan minimal satu latihan untuk masuk leaderboard.',
            ];
        }

        return [
            'layak' => true,
            'peringkat' => $baris['peringkat'],
            'total_peserta' => $data['total_peserta'],
            'skor' => $baris['skor'],
            'latihan_selesai' => $baris['latihan_selesai'],
            'total_latihan' => $baris['total_latihan'],
        ];
    }

    /**
     * Satu hitungan penuh per tingkat per 30 detik.
     *
     * @return array<string, mixed>
     */
    private function data(string $tingkat, ?int $tingkatId = null): array
    {
        $tingkatId ??= $this->tingkatId($tingkat);

        return Cache::remember(
            'leaderboard:'.self::VERSI_CACHE.':'.$tingkatId.':'.$tingkat,
            now()->addSeconds(self::TTL_CACHE_DETIK),
            fn (): array => $this->bangun($tingkat, $tingkatId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bangun(string $tingkat, int $tingkatId): array
    {
        $rows = DB::select(
            $this->cte().' SELECT * FROM ranked ORDER BY peringkat, nama',
            $this->bindings($tingkat, $tingkatId)
        );

        $totalLatihan = $this->totalLatihan($tingkatId);
        $peringkat = [];
        $perPengguna = [];

        foreach ($rows as $r) {
            $baris = [
                'peringkat' => (int) $r->peringkat,
                'nama' => $this->singkat($r->nama),
                'skor' => $this->angka($r->skor),
                'latihan_selesai' => (int) $r->latihan_selesai,
                'total_latihan' => $totalLatihan,
                'total_durasi_detik' => (int) round($r->total_durasi),
            ];

            $peringkat[] = $baris;
            $perPengguna[(int) $r->user_id] = $baris;
        }

        return [
            'batas_pretest' => $this->batas($tingkat),
            'total_peserta' => count($peringkat),
            'total_latihan' => $totalLatihan,
            'peringkat' => $peringkat,
            'per_pengguna' => $perPengguna,
        ];
    }

    private function totalLatihan(int $tingkatId): int
    {
        return DB::table('quiz as q')
            ->join('materi as m', 'm.id', '=', 'q.materi_id')
            ->where('m.tingkat_id', $tingkatId)
            ->count();
    }

    private function bindings(string $tingkat, int $tingkatId): array
    {
        return [
            'tingkat_a' => $tingkatId,
            'batas' => $this->batas($tingkat),
            'tingkat_b' => $tingkatId,
            'model_type' => config('leaderboard.user_model'),
            'admin_role' => config('leaderboard.admin_role'),
        ];
    }

    /** CTE bersama (PostgreSQL); SELECT selalu membaca ranked. */
    private function cte(): string
    {
        return <<<'SQL'
WITH lolos AS (
    SELECT user_id
    FROM pretest
    WHERE tingkat_id = :tingkat_a
      AND nilai IS NOT NULL
    GROUP BY user_id
    HAVING MAX(nilai) >= :batas
),
terbaik AS (
    SELECT DISTINCT ON (qp.user_id, qp.quiz_id)
           qp.user_id,
           qp.quiz_id,
           qp.nilai,
           EXTRACT(EPOCH FROM (COALESCE(qp.disubmit_pada, qp.selesai_pada) - qp.created_at)) AS durasi,
           COALESCE(qp.disubmit_pada, qp.selesai_pada) AS waktu_selesai
    FROM quiz_pengerjaan qp
    JOIN quiz q ON q.id = qp.quiz_id
    JOIN materi m ON m.id = q.materi_id
    WHERE m.tingkat_id = :tingkat_b
      AND qp.nilai IS NOT NULL
      AND (qp.disubmit_pada IS NOT NULL OR qp.selesai_pada IS NOT NULL)
    ORDER BY qp.user_id, qp.quiz_id, qp.nilai DESC, durasi ASC
),
agregat AS (
    SELECT u.id AS user_id,
           u.name AS nama,
           SUM(t.nilai) AS skor,
           SUM(t.durasi) AS total_durasi,
           COUNT(*) AS latihan_selesai,
           MAX(t.waktu_selesai) AS terakhir_selesai
    FROM terbaik t
    JOIN lolos l ON l.user_id = t.user_id
    JOIN users u ON u.id = t.user_id
    WHERE u.deleted_at IS NULL
      AND u.is_active = true
      AND NOT EXISTS (
          SELECT 1
          FROM model_has_roles mr
          JOIN roles r ON r.id = mr.role_id
          WHERE mr.model_id = u.id
            AND mr.model_type = :model_type
            AND r.name = :admin_role
      )
    GROUP BY u.id, u.name
),
ranked AS (
    SELECT a.*,
           RANK() OVER (ORDER BY a.skor DESC, a.total_durasi ASC, a.terakhir_selesai ASC) AS peringkat
    FROM agregat a
)
SQL;
    }

    private function batas(string $tingkat): int
    {
        return (int) config("leaderboard.batas_pretest.{$tingkat}");
    }

    private function angka($nilai): int|float
    {
        return $nilai + 0;
    }

    /** "Rina Amalia Putri" -> "Rina A." */
    private function singkat(?string $nama): string
    {
        $kata = preg_split('/\s+/', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY);

        if (! $kata) {
            return '-';
        }
        if (count($kata) === 1) {
            return $kata[0];
        }

        return $kata[0].' '.mb_strtoupper(mb_substr($kata[1], 0, 1)).'.';
    }
}