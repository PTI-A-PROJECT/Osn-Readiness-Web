<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan soal.jumlah_tampil (dipakai pemilihan soal) dengan rekap
 * riwayat jawaban. Hanya melaporkan; tidak mengubah data.
 *
 * Selisih wajar terjadi karena PurgeAkunJob menghapus permanen akun beserta
 * riwayat jawabannya, sedangkan counter tidak ikut terhapus.
 */
class RekonsiliasiSoalTampil extends Command
{
    protected $signature = 'soal:rekonsiliasi-tampil {--batas=20 : Jumlah baris selisih yang ditampilkan}';

    protected $description = 'Bandingkan soal.jumlah_tampil dengan rekap riwayat jawaban';

    private const AGREGAT = <<<'SQL'
        SELECT soal_id, COUNT(*) AS jumlah
        FROM (
            SELECT soal_id FROM pretest_jawaban
            UNION ALL
            SELECT soal_id FROM quiz_jawaban
            UNION ALL
            SELECT soal_id FROM hasil_simulasi_jawaban
        ) AS riwayat
        GROUP BY soal_id
        SQL;

    public function handle(): int
    {
        $batas = max(1, (int) $this->option('batas'));

        $selisih = DB::select(
            'SELECT s.id, s.jumlah_tampil AS tersimpan, COALESCE(c.jumlah, 0) AS riwayat
             FROM soal s
             LEFT JOIN ('.self::AGREGAT.') c ON c.soal_id = s.id
             WHERE s.jumlah_tampil <> COALESCE(c.jumlah, 0)
             ORDER BY ABS(s.jumlah_tampil - COALESCE(c.jumlah, 0)) DESC, s.id
             LIMIT '.$batas
        );

        if ($selisih === []) {
            $this->info('Tidak ada selisih: counter sama dengan rekap riwayat.');

            return self::SUCCESS;
        }

        $this->warn('Ada selisih antara counter dan rekap riwayat jawaban:');
        $this->table(
            ['soal_id', 'jumlah_tampil', 'riwayat'],
            array_map(fn (object $baris): array => [
                $baris->id,
                $baris->tersimpan,
                $baris->riwayat,
            ], $selisih),
        );

        return self::SUCCESS;
    }
}
