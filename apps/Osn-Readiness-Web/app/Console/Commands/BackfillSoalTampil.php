<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi soal.jumlah_tampil dari riwayat pengerjaan yang sudah ada.
 *
 * Dipakai sekali setelah migrasi kolom. Setelah itu hitungan berjalan sendiri
 * lewat PencatatSoalTampilService saat paket baru dibuat. Command ini menimpa
 * (bukan menambah) sehingga aman dijalankan berulang.
 */
class BackfillSoalTampil extends Command
{
    protected $signature = 'soal:backfill-tampil {--dry-run : Hanya tampilkan perkiraan tanpa menulis}';

    protected $description = 'Isi soal.jumlah_tampil dari riwayat pretest, latihan, dan simulasi';

    /**
     * Agregat kemunculan dari tiga tabel jawaban. Satu baris jawaban = satu
     * kali soal dikirim ke siswa (baris dibuat saat paket disusun).
     */
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
        if ($this->option('dry-run')) {
            $rincian = DB::select(
                'SELECT s.id, s.jumlah_tampil, COALESCE(c.jumlah, 0) AS riwayat
                 FROM soal s
                 LEFT JOIN ('.self::AGREGAT.') c ON c.soal_id = s.id
                 ORDER BY riwayat DESC, s.id
                 LIMIT 10'
            );

            $this->table(
                ['soal_id', 'jumlah_tampil sekarang', 'riwayat'],
                array_map(fn (object $baris): array => [
                    $baris->id,
                    $baris->jumlah_tampil,
                    $baris->riwayat,
                ], $rincian),
            );

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            DB::table('soal')->update(['jumlah_tampil' => 0]);

            // PostgreSQL: UPDATE ... FROM. Proyek ini hanya memakai pgsql
            // (phpunit.xml memaksa DB_CONNECTION=pgsql).
            DB::statement(
                'UPDATE soal s
                 SET jumlah_tampil = c.jumlah
                 FROM ('.self::AGREGAT.') c
                 WHERE c.soal_id = s.id'
            );
        });

        $sudah = (int) DB::table('soal')->where('jumlah_tampil', '>', 0)->count();
        $this->info("Selesai. {$sudah} soal memiliki jumlah_tampil > 0.");

        return self::SUCCESS;
    }
}
