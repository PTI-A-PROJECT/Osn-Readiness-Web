<?php

namespace App\Support;

class NormalisasiProgramSoal
{
    public static function analisis(?string $deskripsi): array
    {
        $teks = trim(str_replace(
            ["\r\n", "\r"],
            "\n",
            (string) $deskripsi
        ));

        $hasil = [
            'status' => 'bukan_kode',
            'pengantar' => null,
            'kode' => null,
            'bahasa' => null,
            'konteks' => $teks !== '' ? $teks : null,
        ];

        if ($teks === '') {
            return $hasil;
        }

        // Cari awal deklarasi atau instruksi Pascal.
        $polaAwal = '/^\s*(program|unit|uses|type|const|var|function|procedure|begin|for|while|repeat)\b/im';

        if (!preg_match($polaAwal, $teks, $match, PREG_OFFSET_CAPTURE)) {
            return $hasil;
        }

        $posisi = $match[0][1];
        $pengantar = trim(substr($teks, 0, $posisi));
        $kode = trim(substr($teks, $posisi));

        // Jangan otomatis memisahkan teks yang mengandung
        // instruksi kontrol sebelum awal kode terdeteksi.
        if (preg_match('/\b(if|then|else|for|while|repeat)\b/i', $pengantar)) {
            $hasil['status'] = 'perlu_review';
            return $hasil;
        }

        // Syarat minimal agar tidak menganggap
        // sembarang teks sebagai program Pascal.
        $memilikiStruktur =
            preg_match('/\bbegin\b/i', $kode) ||
            preg_match('/:=/', $kode);

        if (! $memilikiStruktur) {
            return $hasil;
        }

        // Jangan otomatis mengekstrak kode dari
        // deskripsi yang mempunyai teks lanjutan ambigu.
        if (
            ! preg_match('/(?:end\s*[.;]|writeln?\s*\([^)]*\)\s*;)\s*$/i', $kode)
        ) {
            $hasil['status'] = 'perlu_review';
            return $hasil;
        }

        return [
            'status' => 'terdeteksi',
            'pengantar' => $pengantar !== '' ? $pengantar : null,
            'kode' => $kode,
            'bahasa' => 'pascal',
            'konteks' => null,
        ];
    }

    public static function bersihkanPertanyaan(
        ?string $pertanyaan,
        ?string $kode
    ): string {
        $teks = str_replace(
            ["\r\n", "\r"],
            "\n",
            (string) $pertanyaan
        );

        $kode = trim(str_replace(
            ["\r\n", "\r"],
            "\n",
            (string) $kode
        ));

        if ($kode === '') {
            return trim($teks);
        }

        // Hapus hanya apabila kode muncul secara identik.
        // Jangan gunakan regex untuk menghapus perkiraan kode.
        if (str_contains($teks, $kode)) {
            $teks = str_replace($kode, '', $teks);
        }

        return trim($teks);
    }

}
