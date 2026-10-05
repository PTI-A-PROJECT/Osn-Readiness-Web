<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\Peruntukan;
use App\Enums\TipeSoal;
use App\Models\KonteksSoal;
use App\Models\Materi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk soal pretest topik Data Analisis dengan konteks.
 * Membuat minimal 5 soal (mix pilihan ganda dan isian) untuk testing.
 */
class PretestDataAnalisisSeeder extends Seeder
{
    public function run(): void
    {
        // Dapatkan tingkat pertama (urutan 1)
        $tingkat = TingkatSeleksi::where('urutan', 1)->first();

        if ($tingkat === null) {
            $this->command?->error('Tingkat urutan 1 tidak ditemukan. Jalankan seeder TingkatSeleksiSeeder terlebih dahulu.');
            return;
        }

        // Dapatkan materi pertama untuk tingkat ini
        $materi = Materi::where('tingkat_id', $tingkat->id)
            ->orderBy('urutan')
            ->first();

        if ($materi === null) {
            $this->command?->error('Materi tidak ditemukan untuk tingkat ini. Jalankan seeder KontenContohSeeder terlebih dahulu.');
            return;
        }

        // Buat konteks soal untuk Data Analisis
        $konteksData = $this->buatKonteksSoal($tingkat);

        // Buat soal pretest dengan konteks
        $this->buatSoalPretest($materi, $konteksData);

        $this->command?->info('Seeder PretestDataAnalisis berhasil dijalankan.');
    }

    /**
     * Membuat konteks soal topik Data Analisis.
     *
     * @return array<int, KonteksSoal>
     */
    private function buatKonteksSoal(TingkatSeleksi $tingkat): array
    {
        $konteksData = [];

        $dataKonteks = [
            [
                'judul' => 'Analisis Penjualan Q1 2024',
                'isi_konteks' => 'PT Maju Jaya melakukan analisis penjualan selama kuartal pertama 2024. Data yang dikumpulkan mencakup penjualan per region (Jawa, Sumatera, Kalimantan, Sulawesi, dan Indonesia Timur), dengan pencatatan harian. Ditemukan tren peningkatan penjualan di region Jawa dengan pertumbuhan rata-rata 15% per minggu, sementara di region lain pertumbuhan lebih stabil di angka 5-7% per minggu. Tim analitik menemukan anomali pada minggu kedua dimana terjadi penurunan 20% di semua region yang kemudian diketahui disebabkan oleh hari libur nasional.',
                'gambar' => null,
            ],
            [
                'judul' => 'Dataset Sensor Suhu IoT di Gedung Pintar',
                'isi_konteks' => 'Sebuah gedung perkantoran modern dilengkapi dengan 50 sensor suhu IoT yang tersebar di berbagai lantai dan ruangan. Sensor ini mencatat data suhu setiap menit selama 1 bulan penuh. Hasil analisis menunjukkan pola harian yang konsisten dengan suhu terendah pada pukul 04:00-06:00 (16-18°C) dan tertinggi pada pukul 14:00-16:00 (24-26°C). Ditemukan beberapa outlier (anomali) seperti ruangan tertentu yang mencapai 35°C pada jam kerja normal, mengindikasikan kemungkinan AC yang rusak atau malfungsi.',
                'gambar' => null,
            ],
            [
                'judul' => 'Analisis Kepuasan Pelanggan E-commerce',
                'isi_konteks' => 'Platform e-commerce Toko Mega mengumpulkan feedback dari 50,000 transaksi pelanggan dalam 3 bulan terakhir. Data mencakup rating (1-5 bintang), kategori produk, waktu pembelian, dan komentar pelanggan. Analisis awal menunjukkan rata-rata rating 4.2 bintang dengan standar deviasi 0.8. Kategori elektronik memiliki rating tertinggi (4.5 bintang) sementara kategori fashion memiliki rating terendah (3.8 bintang). Ditemukan korelasi kuat antara waktu pengiriman dan kepuasan pelanggan: pengiriman cepat (1-2 hari) menghasilkan rating 4.6, sedangkan pengiriman lambat (5+ hari) hanya menghasilkan rating 3.5.',
                'gambar' => null,
            ],
            [
                'judul' => 'Studi Pola Lalu Lintas di Jalan Tol',
                'isi_konteks' => 'Badan Jalan Tol Nasional menganalisis data volume kendaraan di 10 titik pengamatan di Tol Jakarta-Bandung selama 6 bulan. Data mencakup jumlah kendaraan per jam, jenis kendaraan (mobil, bus, truk), dan kecepatan rata-rata. Hasil analisis menunjukkan pola peak traffic pada pukul 07:00-09:00 (rush hour pagi) dengan volume mencapai 5,000 kendaraan per jam, dan peak kedua pada pukul 17:00-19:00 (rush hour sore) dengan volume 4,800 kendaraan per jam. Ditemukan bahwa kecelakaan sering terjadi pada kondisi visibility rendah (kabut atau hujan) dan pada segmen jalan dengan kurva tajam.',
                'gambar' => null,
            ],
            [
                'judul' => 'Analisis Performa Siswa Program Beasiswa',
                'isi_konteks' => 'Universitas Pendidikan menganalisis data performa akademik 500 siswa penerima beasiswa penuh selama 2 tahun terakhir. Data mencakup nilai GPA per semester, jumlah jam belajar per minggu, aktivitas ekstrakurikuler, dan latar belakang socio-ekonomi. Hasil menunjukkan rata-rata GPA 3.45 dengan distribusi normal. Siswa yang menghabiskan 20+ jam belajar per minggu memiliki GPA rata-rata 3.7, sementara yang hanya 5-10 jam belajar memiliki GPA 2.9. Ditemukan outlier positif: 12 siswa mencapai GPA 3.9+ meskipun hanya belajar 15 jam per minggu, menunjukkan efisiensi belajar yang tinggi.',
                'gambar' => null,
            ],
        ];

        foreach ($dataKonteks as $data) {
            $konteks = KonteksSoal::create([
                'tingkat_id' => $tingkat->id,
                'judul' => $data['judul'],
                'isi_konteks' => $data['isi_konteks'],
                'gambar' => $data['gambar'],
            ]);
            $konteksData[] = $konteks;
        }

        return $konteksData;
    }

    /**
     * Membuat soal pretest Data Analisis dengan konteks.
     *
     * @param  array<int, KonteksSoal>  $konteksData
     */
    private function buatSoalPretest(Materi $materi, array $konteksData): void
    {
        $soalData = [
            // Soal 1: Pilihan Ganda (konteks Penjualan Q1)
            [
                'konteks_id' => $konteksData[0]->id,
                'level' => Level::Mudah->value,
                'tipe_soal' => TipeSoal::PilihanGanda,
                'pertanyaan' => 'Berdasarkan teks, region manakah yang memiliki pertumbuhan penjualan tertinggi per minggu?',
                'pilihan_jawaban' => [
                    'A' => 'Sumatera',
                    'B' => 'Jawa',
                    'C' => 'Kalimantan',
                    'D' => 'Indonesia Timur',
                ],
                'kunci_jawaban' => 'B',
            ],
            // Soal 2: Isian (konteks Penjualan Q1)
            [
                'konteks_id' => $konteksData[0]->id,
                'level' => Level::Sedang->value,
                'tipe_soal' => TipeSoal::Isian,
                'pertanyaan' => 'Penurunan penjualan sebesar 20% pada minggu kedua disebabkan oleh apa? (Jawab dalam 2-3 kata)',
                'pilihan_jawaban' => null,
                'kunci_jawaban' => 'hari libur nasional',
            ],
            // Soal 3: Pilihan Ganda (konteks IoT Sensor)
            [
                'konteks_id' => $konteksData[1]->id,
                'level' => Level::Mudah->value,
                'tipe_soal' => TipeSoal::PilihanGanda,
                'pertanyaan' => 'Pukul berapa suhu tertinggi biasanya tercatat pada sensor gedung pintar tersebut?',
                'pilihan_jawaban' => [
                    'A' => '04:00-06:00',
                    'B' => '10:00-12:00',
                    'C' => '14:00-16:00',
                    'D' => '20:00-22:00',
                ],
                'kunci_jawaban' => 'C',
            ],
            // Soal 4: Pilihan Ganda (konteks E-commerce)
            [
                'konteks_id' => $konteksData[2]->id,
                'level' => Level::Sedang->value,
                'tipe_soal' => TipeSoal::PilihanGanda,
                'pertanyaan' => 'Kategori produk manakah yang memiliki rating kepuasan pelanggan tertinggi?',
                'pilihan_jawaban' => [
                    'A' => 'Kategori fashion',
                    'B' => 'Kategori elektronik',
                    'C' => 'Kategori furniture',
                    'D' => 'Kategori kecantikan',
                ],
                'kunci_jawaban' => 'B',
            ],
            // Soal 5: Isian (konteks E-commerce)
            [
                'konteks_id' => $konteksData[2]->id,
                'level' => Level::Sulit->value,
                'tipe_soal' => TipeSoal::Isian,
                'pertanyaan' => 'Berapa selisih rating antara pengiriman cepat (1-2 hari) dan pengiriman lambat (5+ hari)?',
                'pilihan_jawaban' => null,
                'kunci_jawaban' => '1.1',
            ],
            // Soal 6: Pilihan Ganda (konteks Lalu Lintas)
            [
                'konteks_id' => $konteksData[3]->id,
                'level' => Level::Mudah->value,
                'tipe_soal' => TipeSoal::PilihanGanda,
                'pertanyaan' => 'Jam berapa terjadi peak traffic (volume tertinggi) pada tol Jakarta-Bandung pagi hari?',
                'pilihan_jawaban' => [
                    'A' => '05:00-07:00',
                    'B' => '07:00-09:00',
                    'C' => '09:00-11:00',
                    'D' => '11:00-13:00',
                ],
                'kunci_jawaban' => 'B',
            ],
            // Soal 7: Pilihan Ganda (konteks Beasiswa)
            [
                'konteks_id' => $konteksData[4]->id,
                'level' => Level::Sedang->value,
                'tipe_soal' => TipeSoal::PilihanGanda,
                'pertanyaan' => 'Berapa rata-rata GPA siswa yang belajar 20+ jam per minggu?',
                'pilihan_jawaban' => [
                    'A' => '2.9',
                    'B' => '3.45',
                    'C' => '3.7',
                    'D' => '3.9',
                ],
                'kunci_jawaban' => 'C',
            ],
            // Soal 8: Isian (konteks Beasiswa)
            [
                'konteks_id' => $konteksData[4]->id,
                'level' => Level::Sulit->value,
                'tipe_soal' => TipeSoal::Isian,
                'pertanyaan' => 'Ada berapa siswa outlier positif yang mencapai GPA 3.9+ meskipun hanya belajar 15 jam per minggu?',
                'pilihan_jawaban' => null,
                'kunci_jawaban' => '12',
            ],
        ];

        foreach ($soalData as $data) {
            Soal::create([
                'id_sumber' => null,
                'tingkat_id' => $materi->tingkat_id,
                'materi_id' => $materi->id,
                'konteks_id' => $data['konteks_id'],
                'level' => $data['level'],
                'peruntukan' => Peruntukan::Pretest->value,
                'tipe_soal' => $data['tipe_soal'],
                'pertanyaan' => $data['pertanyaan'],
                'pilihan_jawaban' => $data['pilihan_jawaban'],
                'kunci_jawaban' => $data['kunci_jawaban'],
                'gambar' => null,
            ]);
        }
    }
}
