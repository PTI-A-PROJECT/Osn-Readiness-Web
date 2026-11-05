<?php

namespace App\Services;

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
 */
class LeaderboardService
{
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
        $tingkatId = $this->tingkatId($tingkat);
        $bindings = $this->bindings($tingkat, $tingkatId);

        $rows = DB::select(
            $this->cte().' SELECT * FROM ranked ORDER BY peringkat, nama LIMIT '.$limit,
            $bindings
        );

        $totalLatihan = $this->totalLatihan($tingkatId);

        return [
            'tingkat' => $tingkat,
            'batas_pretest' => $this->batas($tingkat),
            'total_peserta' => $this->hitungPeserta($bindings),
            'peringkat' => array_map(fn ($r) => [
                'peringkat' => (int) $r->peringkat,
                'nama' => $this->singkat($r->nama),
                'skor' => $this->angka($r->skor),
                'latihan_selesai' => (int) $r->latihan_selesai,
                'total_latihan' => $totalLatihan,
                'total_durasi_detik' => (int) round($r->total_durasi),
            ], $rows),
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

        $bindings = $this->bindings($tingkat, $tingkatId);

        $baris = DB::selectOne(
            $this->cte().' SELECT * FROM ranked WHERE user_id = :uid',
            $bindings + ['uid' => $userId]
        );

        if (! $baris) {
            return [
                'layak' => true,
                'peringkat' => null,
                'alasan' => 'Kerjakan minimal satu latihan untuk masuk leaderboard.',
            ];
        }

        return [
            'layak' => true,
            'peringkat' => (int) $baris->peringkat,
            'total_peserta' => $this->hitungPeserta($bindings),
            'skor' => $this->angka($baris->skor),
            'latihan_selesai' => (int) $baris->latihan_selesai,
            'total_latihan' => $this->totalLatihan($tingkatId),
        ];
    }

    private function hitungPeserta(array $bindings): int
    {
        return (int) DB::selectOne($this->cte().' SELECT COUNT(*) AS n FROM agregat', $bindings)->n;
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

    /** CTE bersama (PostgreSQL). Diikuti SELECT dari: ranked atau agregat. */
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