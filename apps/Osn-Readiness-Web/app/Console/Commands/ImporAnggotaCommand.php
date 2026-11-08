<?php

namespace App\Console\Commands;

use App\Contracts\Services\AnggotaServiceInterface;
use Illuminate\Console\Command;

class ImporAnggotaCommand extends Command
{
    protected $signature = 'anggota:impor
        {file : Path file CSV dengan header nim,nama}
        {--dry-run : Laporkan tanpa menyimpan apa pun}';

    protected $description = 'Buat akun anggota (login dan password awal = NIM) dari file CSV';

    public function handle(AnggotaServiceInterface $anggota): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error("File tidak bisa dibaca: {$file}");

            return self::FAILURE;
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === null) {
                $this->error('Header harus nim,nama.');

                return self::FAILURE;
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(
                fn ($value) => strtolower(trim((string) $value)),
                $header,
            );

            if ($header !== ['nim', 'nama']) {
                $this->error('Header harus nim,nama.');

                return self::FAILURE;
            }

            $baris = [];
            $errors = [];
            $seenNim = [];
            $nomorBaris = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $nomorBaris++;
                $data = array_pad($data, 2, '');
                if (trim(implode('', $data)) === '') {
                    continue;
                }

                $nim = trim((string) $data[0]);
                $nama = preg_replace('/\s+/', ' ', trim((string) $data[1]));
                $valid = true;

                if (! preg_match('/^\d{8,20}$/', $nim)) {
                    $errors[] = "Baris {$nomorBaris}: NIM tidak valid.";
                    $valid = false;
                } elseif (isset($seenNim[$nim])) {
                    $errors[] = "Baris {$nomorBaris}: NIM duplikat.";
                    $valid = false;
                } else {
                    $seenNim[$nim] = true;
                }

                if ($nama === '') {
                    $errors[] = "Baris {$nomorBaris}: nama kosong.";
                    $valid = false;
                }

                if ($valid) {
                    $baris[] = ['nim' => $nim, 'nama' => $nama];
                }
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $hasil = $anggota->impor($baris, (bool) $this->option('dry-run'));
            $dibuat = count(array_filter(
                $hasil,
                fn (array $item) => $item['status'] === 'dibuat',
            ));
            $dilewati = count($hasil) - $dibuat;

            $this->table(
                ['NIM', 'Nama', 'Kode referal', 'Status'],
                array_map(
                    fn (array $item) => [
                        $item['nim'],
                        $item['nama'],
                        $item['kode'] ?? '-',
                        $item['status'],
                    ],
                    $hasil,
                ),
            );

            $this->info("{$dibuat} dibuat, {$dilewati} dilewati.");

            if ($this->option('dry-run')) {
                $this->warn('DRY RUN: tidak ada yang disimpan.');
            }

            return self::SUCCESS;
        } finally {
            fclose($handle);
        }
    }
}
