<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\AturanPemetaanRepositoryInterface;
use App\Enums\Level;
use App\Models\AturanPemetaan;
use App\Services\AturanService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AturanServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_mengubah_parameter_teks_menjadi_tipe(): void
    {
        $aturan = $this->layanan(['passing_grade_pretest' => 70])->untukTingkat(1);

        $this->assertSame(1, $aturan->tingkatId);
        $this->assertSame(1, $aturan->bobotMudah);
        $this->assertSame(2, $aturan->bobotSedang);
        $this->assertSame(2, $aturan->bobotSulit); // bobot_simulasi/2 = 4/2 = 2
        $this->assertSame(30, $aturan->pretestJumlahSoal);
        $this->assertSame(2, $aturan->pretestMinSoalPerMateri);
        $this->assertSame(3, $aturan->jumlahMateriWajib);
        $this->assertSame(0, $aturan->latihanMinSoal); // Default from observer
        $this->assertSame(50.0, $aturan->latihanMinNilai);
        $this->assertSame(3, $aturan->simulasiMaksPercobaan);
        $this->assertSame(70.0, $aturan->passingGrade);
    }

    public function test_persen_level_diindeks_dengan_nilai_enum(): void
    {
        $aturan = $this->layanan(['persen_pretest_sulit' => 25])->untukTingkat(1);

        $this->assertSame(50.0, $aturan->persenPretest(Level::Mudah));
        $this->assertSame(30.0, $aturan->persenPretest(Level::Sedang));
        $this->assertSame(25.0, $aturan->persenPretest(Level::Sulit));

        $this->assertSame(30.0, $aturan->persenSimulasi(Level::Mudah));
        $this->assertSame(40.0, $aturan->persenSimulasi(Level::Sedang));
        $this->assertSame(30.0, $aturan->persenSimulasi(Level::Sulit));
    }

    public function test_urutan_persen_sesuai_mudah_sedang_sulit(): void
    {
        $aturan = $this->layanan()->untukTingkat(1);

        $this->assertSame([50.0, 30.0, 20.0], $aturan->persenPretestBerurutan());
        $this->assertSame([30.0, 40.0, 30.0], $aturan->persenSimulasiBerurutan());
    }

    public function test_bobot_dipilih_sesuai_level(): void
    {
        $aturan = $this->layanan()->untukTingkat(1);

        $this->assertSame(1, $aturan->bobot(Level::Mudah));
        $this->assertSame(2, $aturan->bobot(Level::Sedang));
        $this->assertSame(2, $aturan->bobot(Level::Sulit)); // bobot_simulasi/2 = 4/2 = 2
    }

    public function test_hasil_dicache_per_tingkat(): void
    {
        $repository = Mockery::mock(AturanPemetaanRepositoryInterface::class);

        $repository->shouldReceive('untukTingkat')
            ->once()
            ->with(1)
            ->andReturn($this->baris());

        $layanan = new AturanService($repository);

        $pertama = $layanan->untukTingkat(1);
        $kedua = $layanan->untukTingkat(1);

        $this->assertSame($pertama, $kedua);
    }

    public function test_parameter_yang_hilang_melempar_exception(): void
    {
        $repository = Mockery::mock(AturanPemetaanRepositoryInterface::class);
        $repository->shouldReceive('untukTingkat')->andReturn(
            Collection::make([]) // Empty collection means aturan not found
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak ditemukan');

        (new AturanService($repository))->untukTingkat(1);
    }

    /**
     * @param  array<string, string>  $ubah
     */
    private function layanan(array $ubah = []): AturanService
    {
        $repository = Mockery::mock(AturanPemetaanRepositoryInterface::class);
        $repository->shouldReceive('untukTingkat')->andReturn($this->baris($ubah));

        return new AturanService($repository);
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return Collection<int, AturanPemetaan>
     */
    private function baris(array $ubah = []): Collection
    {
        $default = [
            'tingkat_id' => 1,
            'bobot_pretest' => 1,
            'persen_pretest_mudah' => 50,
            'persen_pretest_sedang' => 30,
            'persen_pretest_sulit' => 20,
            'passing_grade_pretest' => 70,
            'bobot_simulasi' => 4,
            'persen_simulasi_mudah' => 30,
            'persen_simulasi_sedang' => 40,
            'persen_simulasi_sulit' => 30,
            'passing_grade_simulasi' => 70,
            'pretest_jumlah_soal' => 30,
            'pretest_min_soal_per_materi' => 2,
            'jumlah_materi_wajib' => 3,
            'latihan_min_nilai' => 50,
            'simulasi_maks_percobaan' => 3,
        ];

        // Apply overrides
        foreach ($ubah as $key => $value) {
            $default[$key] = is_string($value) ? (int) $value : $value;
        }

        // Create single AturanPemetaan object with all attributes
        return Collection::make([
            new AturanPemetaan($default),
        ]);
    }
}
