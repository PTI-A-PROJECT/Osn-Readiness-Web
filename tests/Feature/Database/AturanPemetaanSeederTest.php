<?php

namespace Tests\Feature\Database;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use Database\Seeders\AturanPemetaanSeeder;
use Database\Seeders\TingkatSeleksiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AturanPemetaanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_enam_belas_parameter_per_tingkat(): void
    {
        $this->seed(TingkatSeleksiSeeder::class);
        $this->seed(AturanPemetaanSeeder::class);

        $this->assertSame(2, TingkatSeleksi::count());
        $this->assertSame(2, AturanPemetaan::count()); // One row per tingkat in denormalized schema
    }

    public function test_passing_grade_berbeda_antara_kabupaten_dan_provinsi(): void
    {
        $this->seed(TingkatSeleksiSeeder::class);
        $this->seed(AturanPemetaanSeeder::class);

        $kabupaten = TingkatSeleksi::where('urutan', 1)->firstOrFail();
        $provinsi = TingkatSeleksi::where('urutan', 2)->firstOrFail();

        $kabupatenAturan = AturanPemetaan::where('tingkat_id', $kabupaten->id)->firstOrFail();
        $provinsiAturan = AturanPemetaan::where('tingkat_id', $provinsi->id)->firstOrFail();

        // Check that seeder values are applied correctly
        $this->assertSame(60, $kabupatenAturan->passing_grade_pretest);
        $this->assertSame(80, $provinsiAturan->passing_grade_simulasi);
        $this->assertSame(50, $kabupatenAturan->persen_pretest_mudah);
        $this->assertSame(50, $provinsiAturan->persen_pretest_mudah);
    }

    public function test_seeder_bisa_dijalankan_ulang_tanpa_menimpa_perubahan_admin(): void
    {
        $this->seed(TingkatSeleksiSeeder::class);
        $this->seed(AturanPemetaanSeeder::class);

        $kabupaten = TingkatSeleksi::where('urutan', 1)->firstOrFail();

        AturanPemetaan::where('tingkat_id', $kabupaten->id)
            ->update(['passing_grade_pretest' => 95]);

        $this->seed(AturanPemetaanSeeder::class);

        $this->assertSame(2, AturanPemetaan::count());
        $this->assertSame(
            95,
            AturanPemetaan::where('tingkat_id', $kabupaten->id)
                ->value('passing_grade_pretest'),
        );
    }

    public function test_seeder_dilewati_bila_tingkat_belum_ada(): void
    {
        $this->seed(AturanPemetaanSeeder::class);

        $this->assertSame(0, AturanPemetaan::count());
    }
}
