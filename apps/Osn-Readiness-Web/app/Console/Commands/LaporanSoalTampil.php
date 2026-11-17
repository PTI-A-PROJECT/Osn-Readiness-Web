<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cetak laporan Markdown kemunculan seluruh soal.
 *
 * Dipakai untuk dokumentasi/audit distribusi soal: seluruh soal, berapa kali
 * sudah keluar, dan porsinya terhadap total kemunculan pada peruntukan yang
 * sama. Output murni Markdown, jadi bisa diarahkan langsung ke file:
 *
 *   docker compose exec -T backend php artisan soal:laporan-tampil \
 *     > .commandcode/reports/laporan-tampil-soal.md
 */
class LaporanSoalTampil extends Command
{
    protected $signature = 'soal:laporan-tampil';

    protected $description = 'Cetak laporan Markdown kemunculan seluruh soal';

    public function handle(): int
    {
        $ringkasan = DB::select(
            'SELECT peruntukan,
                    count(*) AS soal,
                    count(*) FILTER (WHERE jumlah_tampil = 0) AS belum,
                    sum(jumlah_tampil) AS total,
                    max(jumlah_tampil) AS maks,
                    round(avg(jumlah_tampil), 2) AS rata
             FROM soal
             GROUP BY peruntukan
             ORDER BY peruntukan'
        );

        $soal = DB::select(
            "SELECT id, peruntukan, level, jumlah_tampil,
                    left(regexp_replace(pertanyaan, '\\s+', ' ', 'g'), 90) AS cuplikan
             FROM soal
             ORDER BY peruntukan, jumlah_tampil DESC, id"
        );

        $totalPerPeruntukan = [];
        $jumlahPerPeruntukan = [];

        foreach ($ringkasan as $baris) {
            $totalPerPeruntukan[$baris->peruntukan] = (int) $baris->total;
            $jumlahPerPeruntukan[$baris->peruntukan] = (int) $baris->soal;
        }

        $tulis = function (string $baris = ''): void {
            $this->output->writeln($baris);
        };

        $tulis('# Laporan Kemunculan Soal');
        $tulis();
        $tulis('Dibuat: '.now()->format('Y-m-d H:i:s'));
        $tulis();
        $tulis('Sumber angka: kolom `soal.jumlah_tampil`. Nilai ini naik **sekali per paket**');
        $tulis('pengerjaan saat paket dibuat (pre-test, latihan, simulasi) — bukan saat soal');
        $tulis('dijawab dan bukan saat halaman dibuka ulang.');
        $tulis();
        $tulis('Pemilihan soal memakai **urutan angka ini** (terkecil lebih dulu, acak di antara');
        $tulis('angka yang sama). Tidak ada pembobotan/persentase peluang pada picker.');
        $tulis();
        $tulis('## Ringkasan');
        $tulis();
        $tulis('| Peruntukan | Soal | Belum pernah keluar | Total kemunculan | Maks | Rata-rata |');
        $tulis('| --- | ---: | ---: | ---: | ---: | ---: |');

        foreach ($ringkasan as $baris) {
            $tulis(sprintf(
                '| %s | %d | %d | %d | %d | %s |',
                $baris->peruntukan,
                $baris->soal,
                $baris->belum,
                $baris->total,
                $baris->maks,
                $baris->rata,
            ));
        }

        $tulis();
        $tulis('## Daftar seluruh soal');
        $tulis();
        $tulis('Kolom **Porsi** = kemunculan soal ÷ total kemunculan pada peruntukan yang sama.');
        $tulis('Urutan: kemunculan terbanyak lebih dulu.');

        $peruntukanAktif = null;

        foreach ($soal as $baris) {
            if ($baris->peruntukan !== $peruntukanAktif) {
                $peruntukanAktif = $baris->peruntukan;

                $tulis();
                $tulis(sprintf(
                    '### %s (%d soal, %d total kemunculan)',
                    ucfirst($peruntukanAktif),
                    $jumlahPerPeruntukan[$peruntukanAktif] ?? 0,
                    $totalPerPeruntukan[$peruntukanAktif] ?? 0,
                ));
                $tulis();
                $tulis('| ID | Level | Tampil | Porsi | Pertanyaan |');
                $tulis('| ---: | --- | ---: | ---: | --- |');
            }

            $total = $totalPerPeruntukan[$baris->peruntukan] ?? 0;
            $porsi = $total > 0 ? round($baris->jumlah_tampil / $total * 100, 2) : 0.0;
            $cuplikan = str_replace(
                ['|', '`'],
                ['\\|', "'"],
                trim((string) $baris->cuplikan),
            );

            $tulis(sprintf(
                '| %d | %s | %d | %s%% | %s |',
                $baris->id,
                $baris->level,
                $baris->jumlah_tampil,
                number_format($porsi, 2, ',', '.'),
                $cuplikan,
            ));
        }

        $tulis();
        $tulis('## Catatan');
        $tulis();
        $tulis('- Angka tidak bertambah saat paket dilanjutkan (resume) atau dibuka ulang.');
        $tulis('- Akun yang dihapus permanen (`PurgeAkunJob`) ikut menghapus baris jawabannya.');
        $tulis('  Counter **tidak** berkurang, sehingga angka bisa lebih besar dari rekap riwayat.');
        $tulis('- Regenerasi laporan ini:');
        $tulis();
        $tulis('  ```bash');
        $tulis('  docker compose exec -T backend php artisan soal:laporan-tampil \\');
        $tulis('    > .commandcode/reports/laporan-tampil-soal.md');
        $tulis('  ```');
        $tulis();
        $tulis('- Perintah terkait: `soal:backfill-tampil` (isi awal dari riwayat),');
        $tulis('  `soal:rekonsiliasi-tampil` (bandingkan counter dengan riwayat).');
        $tulis('- Matikan prioritas distribusi tanpa rollback: `OSN_PRIORITAS_SOAL_JARANG=false`.');

        return self::SUCCESS;
    }
}
