<?php

namespace App\Services;

use App\Contracts\Clients\PerhitunganClientInterface;
use App\Contracts\Repositories\HasilSimulasiJawabanRepositoryInterface;
use App\Contracts\Repositories\HasilSimulasiRepositoryInterface;
use App\Contracts\Repositories\KenaikanTingkatRepositoryInterface;
use App\Contracts\Repositories\PretestRepositoryInterface;
use App\Contracts\Repositories\SimulasiRepositoryInterface;
use App\Contracts\Repositories\SoalRepositoryInterface;
use App\Contracts\Repositories\TingkatSeleksiRepositoryInterface;
use App\Contracts\Services\AturanServiceInterface;
use App\Contracts\Services\KelulusanServiceInterface;
use App\Contracts\Services\PutaranServiceInterface;
use App\Contracts\Services\SimulasiServiceInterface;
use App\Contracts\Services\SoalPickerServiceInterface;
use App\Contracts\Services\SyaratSimulasiServiceInterface;
use App\DTOs\PermintaanSoal;
use App\DTOs\SimulasiDimulai;
use App\Enums\JenisPengerjaan;
use App\Enums\Peruntukan;
use App\Exceptions\KuotaSimulasiHabisException;
use App\Exceptions\PerhitunganTidakTersediaException;
use App\Exceptions\SimulasiBelumDinilaiException;
use App\Exceptions\SudahLulusException;
use App\Exceptions\SyaratSimulasiBelumTerpenuhiException;
use App\Exceptions\TingkatTerkunciException;
use App\Exceptions\WaktuHabisException;
use App\Jobs\NilaiUlangJob;
use App\Models\HasilSimulasi;
use App\Models\HasilSimulasiJawaban;
use App\Models\Pretest;
use App\Models\Simulasi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimulasiService implements SimulasiServiceInterface
{
    /** Toleransi keterlambatan jawaban setelah batas (detik, keputusan #3). */
    private const TOLERANSI_DETIK = 30;

    public function __construct(
        private readonly HasilSimulasiRepositoryInterface $hasilRepository,
        private readonly HasilSimulasiJawabanRepositoryInterface $jawabanRepository,
        private readonly PretestRepositoryInterface $pretestRepository,
        private readonly KenaikanTingkatRepositoryInterface $kenaikanRepository,
        private readonly SoalRepositoryInterface $soalRepository,
        private readonly PutaranServiceInterface $putaranService,
        private readonly SyaratSimulasiServiceInterface $syaratService,
        private readonly SoalPickerServiceInterface $soalPicker,
        private readonly SimulasiRepositoryInterface $simulasiRepository,
        private readonly AturanServiceInterface $aturanService,
        private readonly TingkatSeleksiRepositoryInterface $tingkatRepository,
        private readonly PerhitunganClientInterface $perhitungan,
        private readonly KelulusanServiceInterface $kelulusanService,
    ) {}

    /**
     * Daftar simulasi satu tingkat beserta sisa kuota percobaannya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftar(User $user, int $tingkatId): array
    {
        $tingkat = $this->tingkatModel($tingkatId);
        $status = $this->putaranService->status($user, $tingkat);

        if (! $status->tingkatTerbuka) {
            throw new TingkatTerkunciException("Tingkat {$tingkat->nama_tingkat} belum terbuka.");
        }

        $aturan = $this->aturanService->untukTingkat($tingkatId);
        $maksimal = (int) $aturan->simulasiMaksPercobaan;

        $daftar = [];
        $simulasiTingkat = $this->simulasiRepository->untukTingkat($tingkatId);

        foreach ($simulasiTingkat as $simulasi) {
            $terpakai = $status->putaranAktifId === null
                ? 0
                : $this->hasilRepository->jumlahPercobaan((int) $status->putaranAktifId);

            $daftar[] = [
                'id' => (int) $simulasi->id,
                'nama_simulasi' => (string) $simulasi->nama_simulasi,
                'jumlah_soal' => (int) $simulasi->jumlah_soal,
                'durasi_menit' => (int) $simulasi->durasi_menit,
                'is_aktif' => (bool) $simulasi->is_aktif,
                'sisa_kuota' => max(0, $maksimal - $terpakai),
            ];
        }

        return $daftar;
    }

    /**
     * Mulai percobaan, atau lanjutkan yang sedang berjalan. Urutan cek 1–9
     * sesuai dokumen, semuanya di dalam satu transaksi yang mengunci baris
     * pretest putaran aktif.
     */
    public function mulai(User $user, Simulasi $simulasi): SimulasiDimulai
    {
        $tingkat = $simulasi->tingkat;

        // 1. Simulasi harus aktif dan tingkatnya terbuka.
        if (! $simulasi->is_aktif) {
            throw (new ModelNotFoundException)->setModel(Simulasi::class, [$simulasi->id]);
        }

        $statusAwal = $this->putaranService->status($user, $tingkat);

        if (! $statusAwal->tingkatTerbuka) {
            throw new TingkatTerkunciException("Tingkat {$tingkat->nama_tingkat} belum terbuka.");
        }

        return DB::transaction(function () use ($user, $simulasi, $tingkat): SimulasiDimulai {
            // 2. Kunci baris pretest putaran aktif. Tanpa putaran aktif,
            // syarat simulasi pasti belum terpenuhi.
            $putaranAktif = $this->pretestRepository->putaranAktifTerbaru($user->id, (int) $tingkat->id);

            if (! $putaranAktif instanceof Pretest) {
                throw new SyaratSimulasiBelumTerpenuhiException('Simulasi membutuhkan pre-test yang berjalan penuh.');
            }

            $terkunci = $this->pretestRepository->findUntukUpdate($putaranAktif->id);

            if (! $terkunci instanceof Pretest) {
                throw new SyaratSimulasiBelumTerpenuhiException('Putaran aktif tidak ditemukan.');
            }

            // 3. Sudah lulus tingkat itu.
            if ($this->kenaikanRepository->adaLulus($user->id, (int) $tingkat->id)) {
                throw new SudahLulusException("Tingkat {$tingkat->nama_tingkat} sudah lulus.");
            }

            // 4. Ada simulasi berjalan: kembalikan, tolak, atau tutup dulu.
            $berjalan = $this->hasilRepository->berjalan($user->id, (int) $terkunci->id);

            if ($berjalan instanceof HasilSimulasi) {
                return $this->lanjutan($berjalan);
            }

            $aturan = $this->aturanService->untukTingkat((int) $tingkat->id);

            $terpakai = $this->hasilRepository->jumlahPercobaan((int) $terkunci->id);

            // 5. Kuota percobaan habis.
            if ($terpakai >= (int) $aturan->simulasiMaksPercobaan) {
                throw new KuotaSimulasiHabisException('Semua percobaan simulasi sudah dipakai.');
            }

            // 6. Syarat simulasi belum terpenuhi. Rincian lengkapnya sudah
            // tersedia lewat GET /api/simulasi/syarat/{tingkat}; di sini
            // dibalas kode bisnisnya beserta ringkasan jumlah yang kurang.
            $syarat = $this->syaratService->periksa($user, $tingkat);

            if (! $syarat->terpenuhi) {
                $belum = count(array_filter(
                    $syarat->rincian,
                    fn ($baris): bool => ! ($baris['selesai'] ?? false)
                        || ($baris['nilai_latihan'] ?? 0) < ($baris['batas'] ?? 0),
                ));

                throw new SyaratSimulasiBelumTerpenuhiException(
                    detail: "{$belum} dari ".count($syarat->rincian).' materi wajib belum memenuhi syarat.',
                );
            }

            // 7. Daftar dihindari = semua soal dari percobaan lain putaran ini.
            $terpilih = $this->soalPicker->pilih(new PermintaanSoal(
                tingkatId: (int) $tingkat->id,
                peruntukan: Peruntukan::Simulasi,
                jumlahSoal: (int) $simulasi->jumlah_soal,
                persenLevel: $aturan->persenLevelSimulasi,
                soalDihindari: $this->jawabanRepository->soalIdsTerpakaiPutaran((int) $terkunci->id),
            ));

            // 8. Buat percobaan dan baris jawaban, lalu tutup transaksi.
            $mulaiPada = now();

            $hasil = $this->hasilRepository->create([
                'user_id' => $user->id,
                'simulasi_id' => $simulasi->id,
                'pretest_id' => $terkunci->id,
                'mulai_pada' => $mulaiPada,
                'batas_pada' => $mulaiPada->copy()->addMinutes((int) $simulasi->durasi_menit),
            ]);

            $this->jawabanRepository->buatBanyak($hasil->id, $terpilih->butir);

            return $this->balasan($hasil, baru: true);
        });
    }

    public function ringkasan(User $user, int $hasilId): SimulasiDimulai
    {
        return $this->balasan($this->hasilMilik($user, $hasilId), baru: false);
    }

    public function simpanJawaban(User $user, int $hasilId, int $soalId, ?string $jawaban): void
    {
        $hasil = $this->hasilMilik($user, $hasilId);

        // Waktu server melewati batas ditambah toleransi.
        if (now()->greaterThan($hasil->batas_pada->copy()->addSeconds(self::TOLERANSI_DETIK))) {
            throw new WaktuHabisException('Waktu pengerjaan simulasi sudah habis.');
        }

        if ($hasil->disubmit_pada !== null) {
            throw ValidationException::withMessages([
                'hasil' => ['Jawaban sudah dikunci saat simulasi disubmit.'],
            ]);
        }

        $baris = $this->jawabanRepository->findJawaban($hasil->id, $soalId);

        if (! $baris instanceof HasilSimulasiJawaban) {
            throw ValidationException::withMessages([
                'soal_id' => ['Soal ini bukan bagian dari simulasi tersebut.'],
            ]);
        }

        $baris->forceFill(['jawaban_user' => $jawaban])->save();
    }

    public function submit(User $user, int $hasilId): HasilSimulasi
    {
        $hasil = $this->hasilMilik($user, $hasilId);

        return $this->submitInternal($hasil);
    }

    /**
     * Alur submit yang sama untuk request siswa, command penutup, dan retry
     * job: kunci, panggil Python di luar transaksi, simpan + nilai dalam
     * transaksi kedua.
     */
    public function submitInternal(HasilSimulasi $hasil): HasilSimulasi
    {
        // Transaksi 1: kunci baris. Bila selesai, kembalikan yang ada.
        $terkunci = DB::transaction(function () use ($hasil): HasilSimulasi {
            $baris = $this->hasilRepository->findUntukUpdate($hasil->id);

            if (! $baris instanceof HasilSimulasi) {
                throw (new ModelNotFoundException)->setModel(HasilSimulasi::class, [$hasil->id]);
            }

            if ($baris->selesai_pada === null && $baris->disubmit_pada === null) {
                $baris->forceFill(['disubmit_pada' => now()])->save();
            }

            return $baris;
        });

        if ($terkunci->selesai_pada !== null) {
            return $terkunci->load('jawaban');
        }

        try {
            $this->selesaikanPenilaian($terkunci->id);
        } catch (PerhitunganTidakTersediaException $exception) {
            NilaiUlangJob::dispatch(JenisPengerjaan::Simulasi, $terkunci->id);

            throw new PerhitunganTidakTersediaException(
                "Penilaian simulasi #{$terkunci->id} gagal dan dijadwalkan untuk dicoba lagi.",
                $exception->getDetail(),
            );
        }

        return $this->hasilRepository->findUntukUpdate($terkunci->id) ?? $terkunci;
    }

    /**
     * Susun payload, panggil Python di luar transaksi, simpan hasil beserta
     * keputusan kelulusan dalam transaksi kedua. Idempoten untuk job.
     */
    public function selesaikanPenilaian(int $id): void
    {
        $hasil = $this->hasilRepository->findUntukUpdate($id);

        if (! $hasil instanceof HasilSimulasi || $hasil->selesai_pada !== null) {
            return;
        }

        $jawaban = $this->jawabanRepository->untukHasil($id);

        $balasan = $this->perhitungan->hitungPenilaian($this->payloadSoal($jawaban));

        $statusBenar = collect($balasan['jawaban'])->keyBy('soal_id');
        $benar = 0;
        $salah = 0;

        foreach ($jawaban as $baris) {
            $status = $statusBenar->get((int) $baris->soal_id);

            if ((bool) ($status['status_benar'] ?? false)) {
                $benar++;
            } else {
                $salah++;
            }
        }

        DB::transaction(function () use ($hasil, $jawaban, $balasan, $statusBenar, $benar, $salah): void {
            foreach ($jawaban as $baris) {
                $status = $statusBenar->get((int) $baris->soal_id);

                $baris->forceFill([
                    'status_benar' => (bool) ($status['status_benar'] ?? false),
                ])->save();
            }

            $hasil->forceFill([
                'nilai' => $balasan['nilai'],
                'jumlah_benar' => $benar,
                'jumlah_salah' => $salah,
                'selesai_pada' => now(),
            ])->save();

            // Kelulusan ikut dijalankan di sini, sehingga penilaian yang
            // berasal dari job ulang tetap menulis kenaikan tingkat.
            $this->kelulusanService->menilai($hasil->fresh() ?? $hasil);
        });
    }

    /**
     * Tutup semua percobaan kedaluwarsa atas nama sistem. Mengembalikan
     * jumlah yang berhasil ditutup.
     */
    public function tutupKedaluwarsa(): int
    {
        $ditutup = 0;

        foreach ($this->hasilRepository->kedaluwarsa(self::TOLERANSI_DETIK) as $hasil) {
            try {
                $this->submitInternal($hasil);
                $ditutup++;
            } catch (PerhitunganTidakTersediaException $exception) {
                // Sudah dikirim ke job oleh submitInternal; lanjut ke
                // percobaan berikutnya supaya satu kegagalan tidak menahan
                // seluruh jadwal.
                continue;
            }
        }

        return $ditutup;
    }

    /**
     * Percobaan berjalan yang ditemukan di langkah 4: bila belum disubmit
     * dan masih dalam batas, kembalikan; bila sudah disubmit tetapi belum
     * dinilai, tolak; bila batasnya lewat, tutup dulu lewat alur submit
     * lalu laporkan bahwa percobaan baru harus dimulai lagi.
     */
    private function lanjutan(HasilSimulasi $berjalan): SimulasiDimulai
    {
        if ($berjalan->disubmit_pada === null && ! $this->lewatBatas($berjalan)) {
            return $this->balasan($berjalan, baru: false);
        }

        if ($berjalan->disubmit_pada !== null && $berjalan->selesai_pada === null) {
            throw new SimulasiBelumDinilaiException('Percobaan ini sedang dinilai; tunggu sebentar.');
        }

        // Batas lewat tetapi scheduler belum menutup: tutup sekarang lewat
        // alur submit yang sama, supaya tidak ada jawaban yang hilang.
        $this->submitInternal($berjalan);

        throw new SimulasiBelumDinilaiException('Waktu percobaan ini sudah habis dan baru saja dinilai. Mulai percobaan baru.');
    }

    private function lewatBatas(HasilSimulasi $hasil): bool
    {
        return now()->greaterThan($hasil->batas_pada->copy()->addSeconds(self::TOLERANSI_DETIK));
    }

    /**
     * @param  Collection<int, HasilSimulasiJawaban>  $jawaban
     * @return list<array{soal_id: int, tipe_soal: string, bobot: int, jawaban_user: ?string, kunci_jawaban: string}>
     */
    private function payloadSoal(Collection $jawaban): array
    {
        $payload = [];

        foreach ($jawaban as $baris) {
            /** @var Soal $soal */
            $soal = $baris->soal;

            $payload[] = [
                'soal_id' => (int) $soal->id,
                'tipe_soal' => $soal->tipe_soal->value,
                'bobot' => (int) $baris->bobot,
                'jawaban_user' => $baris->jawaban_user,
                'kunci_jawaban' => (string) $soal->kunci_jawaban,
            ];
        }

        return $payload;
    }

    private function hasilMilik(User $user, int $hasilId): HasilSimulasi
    {
        $hasil = $this->hasilRepository->findMilik($hasilId, $user->id);

        if (! $hasil instanceof HasilSimulasi) {
            throw (new ModelNotFoundException)->setModel(HasilSimulasi::class, [$hasilId]);
        }

        return $hasil;
    }

    private function tingkatModel(int $tingkatId): TingkatSeleksi
    {
        $tingkat = $this->tingkatRepository->find($tingkatId);

        if (! $tingkat instanceof TingkatSeleksi) {
            throw (new ModelNotFoundException)->setModel(TingkatSeleksi::class, [$tingkatId]);
        }

        return $tingkat;
    }

    private function balasan(HasilSimulasi $hasil, bool $baru): SimulasiDimulai
    {
        $jawaban = $this->jawabanRepository->untukHasil($hasil->id);

        $peta = [];

        foreach ($jawaban as $baris) {
            $peta[(int) $baris->soal_id] = $baris->soal;
        }

        $hasil->setRelation('jawaban', $jawaban);
        $hasil->setRelation('soal', new Collection(array_values($peta)));

        $hasil->loadMissing(['simulasi.tingkat']);

        return new SimulasiDimulai(
            hasil: $hasil,
            soal: new Collection(array_values($peta)),
            baru: $baru,
        );
    }
}
