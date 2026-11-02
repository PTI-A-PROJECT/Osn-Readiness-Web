<?php

namespace App\Console\Commands;

use App\Models\Soal;
use App\Support\NormalisasiProgramSoal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class NormalisasiKodeSoal extends Command
{
    protected $signature = 'soal:normalisasi-kode
    {folder : Folder berisi soal_*.json}
    {--tingkat= : kabupaten atau provinsi}
    {--whitelist= : File daftar id_sumber yang telah diverifikasi}
    {--apply : Simpan perubahan, default hanya preview}';

    protected $description =
        'Preview dan normalisasi kode soal dengan whitelist';

    public function handle(): int
    {
        $folder = rtrim(
            (string) $this->argument('folder'),
            '/\\'
        );

        $tingkat = $this->option('tingkat');

        if (
            $tingkat !== null &&
            ! in_array($tingkat, ['kabupaten', 'provinsi'], true)
        ) {
            $this->error('Tingkat harus kabupaten atau provinsi.');
            return self::FAILURE;
        }

        $pola = match ($tingkat) {
            'kabupaten' => '/soal_kab_*.json',
            'provinsi' => '/soal_prov_*.json',
            default => '/soal_*.json',
        };

        $files = glob($folder . $pola) ?: [];
        $apply = (bool) $this->option('apply');
        $whitelistPath = $this->option('whitelist');

        if ($files === []) {
            $this->error('File soal JSON tidak ditemukan.');
            return self::FAILURE;
        }

        $izin = null;

        if ($apply && ! $whitelistPath) {
            $this->error(
                'Mode apply wajib menggunakan --whitelist.'
            );
            return self::FAILURE;
        }

        if ($whitelistPath) {
            if (! is_file($whitelistPath)) {
                $this->error('File whitelist tidak ditemukan.');
                return self::FAILURE;
            }

            $baris = file(
                $whitelistPath,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if ($baris === false) {
                $this->error('Gagal membaca whitelist.');
                return self::FAILURE;
            }

            $ids = array_filter(
                array_map('trim', $baris),
                fn ($id) => $id !== '' &&
                    ! str_starts_with($id, '#')
            );

            $izin = array_fill_keys($ids, true);

            if ($izin === []) {
                $this->error('Whitelist kosong.');
                return self::FAILURE;
            }
        }

        $statistik = [
            'total' => 0,
            'terdeteksi' => 0,
            'perlu_review' => 0,
            'bukan_kode' => 0,
            'duplikasi' => 0,
            'kandidat' => 0,
            'dilewati' => 0,
            'tersimpan' => 0,
        ];

        $kandidat = [];
        $seen = [];

        foreach ($files as $file) {
            try {
                $items = json_decode(
                    file_get_contents($file),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (Throwable $e) {
                $this->error(
                    basename($file) . ': ' . $e->getMessage()
                );
                return self::FAILURE;
            }

            if (! is_array($items)) {
                $this->error('Format JSON tidak valid.');
                return self::FAILURE;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $this->error('Item soal bukan objek.');
                    return self::FAILURE;
                }

                $statistik['total']++;

                $id = (string) ($item['id_sumber'] ?? '');

                if ($id === '' || isset($seen[$id])) {
                    $this->error(
                        "ID kosong atau duplikat: {$id}"
                    );
                    return self::FAILURE;
                }

                $seen[$id] = true;

                $hasil = NormalisasiProgramSoal::analisis(
                    $item['deskripsi_soal'] ?? null
                );

                $status = $hasil['status'];

                if (! isset($statistik[$status])) {
                    $this->error(
                        "Status tidak dikenal: {$status}"
                    );
                    return self::FAILURE;
                }

                $statistik[$status]++;

                if ($status !== 'terdeteksi') {
                    continue;
                }

                $kode = (string) ($hasil['kode'] ?? '');
                $pertanyaan = (string) ($item['soal'] ?? '');

                if ($kode === '' || trim($pertanyaan) === '') {
                    $statistik['dilewati']++;
                    continue;
                }

                // Kode di dalam pertanyaan wajib ditinjau
                // secara khusus, tidak diproses otomatis.
                if (str_contains($pertanyaan, $kode)) {
                    $statistik['duplikasi']++;
                    continue;
                }

                if ($izin !== null && ! isset($izin[$id])) {
                    $statistik['dilewati']++;
                    continue;
                }

                $statistik['kandidat']++;

                $kandidat[] = [
                    'id' => $id,
                    'pengantar' => $hasil['pengantar'],
                    'kode' => $kode,
                    'bahasa' => $hasil['bahasa'],
                ];
            }
        }

        $this->info(
            $apply ? 'MODE APPLY' : 'MODE PREVIEW'
        );

        $this->table(
            ['Kategori', 'Jumlah'],
            collect($statistik)
                ->map(fn ($jumlah, $nama) => [
                    $nama,
                    $jumlah,
                ])
                ->values()
                ->all()
        );

        $this->line('Contoh kandidat:');

        foreach (array_slice($kandidat, 0, 10) as $item) {
            $this->line(
                $item['id'] . ' [' . $item['bahasa'] . ']'
            );
        }

        if (! $apply) {
            $this->info('Preview selesai. Database tidak diubah.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use (
                $kandidat,
                &$statistik
            ): void {
                foreach ($kandidat as $item) {
                    $soal = Soal::query()
                        ->where('id_sumber', $item['id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $soal) {
                        throw new \RuntimeException(
                            "Soal tidak ditemukan: {$item['id']}"
                        );
                    }

                    // Tidak menimpa data program yang sudah ada.
                    if (
                        $soal->kode !== null ||
                        $soal->pengantar !== null ||
                        $soal->bahasa !== null
                    ) {
                        throw new \RuntimeException(
                            "Kolom program sudah terisi: {$item['id']}"
                        );
                    }

                    $soal->update([
                        'pengantar' => $item['pengantar'],
                        'kode' => $item['kode'],
                        'bahasa' => $item['bahasa'],
                    ]);

                    $statistik['tersimpan']++;
                }
            });
        } catch (Throwable $e) {
            $this->error(
                'Perubahan dibatalkan: ' . $e->getMessage()
            );
            return self::FAILURE;
        }

        $this->info(
            "Berhasil menyimpan {$statistik['tersimpan']} soal."
        );

        return self::SUCCESS;
    }
}
