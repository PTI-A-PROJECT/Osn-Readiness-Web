<?php

namespace Database\Seeders;

use App\Contracts\Services\AnggotaServiceInterface;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Mendaftarkan akun anggota referral. Login dan password awal = NIM.
 *
 * Cara pakai di server (aman dijalankan berulang; NIM yang sudah ada dilewati):
 *   1. Tambah baris ke ANGGOTA di bawah, lalu deploy.
 *   2. php artisan db:seed --class=AnggotaSeeder --force
 *
 * Sengaja tidak ada di DatabaseSeeder supaya tidak jalan otomatis.
 */
class AnggotaSeeder extends Seeder
{
    /**
     * [NIM, NAMA]. Kode referal dibuat otomatis: OSN + kata nama yang masih bebas.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const ANGGOTA = [
        ['230533610158', 'ACHMAD ARIF SETIAWAN'],
        ['230533605308', 'ACHMAD IBRA ALFAHRO'],
        ['230533608093', 'ADAM ABDILLAH MAHMUD'],
        ['230533609786', 'ADEO MARCELINO'],
        ['230533604609', 'ADINDA DWI KURNIA'],
        ['230533609836', 'ADITYA EKA RAHMADANI'],
        ['230533609321', 'AHMAD NABIL MUJADDID'],
        ['230533610044', 'AHMAD SURYA ADI KUSUMA'],
        ['230533600777', 'AISYA ALYANI SANTOSO'],
        ['230533605824', 'ALI URAIDHI ASSAGAF'],
        ['230533605510', 'AMALIA PRAMESWARI ALVINA'],
        ['230533605618', 'AMELIA NOVELINDA'],
        ['230533602149', 'ANANDA DHIAZ NIKOLA'],
        ['230533606165', 'ANGGA RIZWAR'],
        ['230533601886', 'ANISATUL QOMARIYAH'],
        ['230533609139', 'ARFAN MIRZA RAMADANDI'],
        ['230533601286', 'AULIA ELVIRA ADITAMA'],
        ['230533610424', 'BIMO NUGROHO TARAQQI'],
        ['230533609472', 'DAFFA RAMADHAN DAMAR PANULUH'],
        ['230533610533', 'DAVINA ARAMITA NABILAH'],
        ['230533607297', 'DEA ALENSA'],
        ['230533607602', 'DEMIA ZAHARA AMANDASARI'],
        ['230533608655', 'FAJAR PRAMUDYANA ANANTA PURBA'],
        ['230533605506', 'FAKHRI RAFI` AHMADI'],
        ['230533605767', 'FALIH HAIBAN FITRAHADI'],
        ['230533608625', 'GUNTUR NAUFAL IMADUDDIN'],
        ['230533605599', 'HARIS HAMMAN AL-ADL'],
        ['230533609529', 'HILMI ZAKIN PUTRA'],
        ['230533606674', 'IBNU PRASETIA NUGROHO'],
        ['230533608035', 'JANUARTA MADI PRATAMA PUTRA'],
        ['230533605312', 'MAHANAIM ELYANDA PUTRA KURNIAWAN KASIM'],
        ['230533608811', 'MUHAMMAD HANIF ABDUR RAZAQ'],
    ];

    public function run(AnggotaServiceInterface $anggota): void
    {
        $this->jalankan($anggota, self::ANGGOTA);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $data
     */
    public function jalankan(AnggotaServiceInterface $anggota, array $data): void
    {
        $baris = [];
        $seen = [];

        foreach ($data as $i => [$nim, $nama]) {
            $nim = trim($nim);
            $nama = preg_replace('/\s+/', ' ', trim($nama));
            $no = $i + 1;

            if (! preg_match('/^\d{8,20}$/', $nim)) {
                throw new InvalidArgumentException("Anggota #{$no}: NIM tidak valid ({$nim}).");
            }
            if (isset($seen[$nim])) {
                throw new InvalidArgumentException("Anggota #{$no}: NIM duplikat ({$nim}).");
            }
            if ($nama === '') {
                throw new InvalidArgumentException("Anggota #{$no}: nama kosong.");
            }

            $seen[$nim] = true;
            $baris[] = ['nim' => $nim, 'nama' => $nama];
        }

        if ($baris === []) {
            $this->command?->warn('Daftar ANGGOTA kosong; tidak ada yang dibuat.');

            return;
        }

        $hasil = $anggota->impor($baris, false);

        $this->command?->table(
            ['NIM', 'Nama', 'Kode referal', 'Status'],
            array_map(fn (array $h) => [$h['nim'], $h['nama'], $h['kode'] ?? '-', $h['status']], $hasil),
        );
    }
}
