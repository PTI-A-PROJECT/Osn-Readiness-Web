<?php

namespace Tests\Feature\Api;

use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Pretest;
use App\Models\ProgressBelajar;
use App\Models\Quiz;
use App\Models\QuizPengerjaan;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MateriBelajarApiTest extends TestCase
{
    use RefreshDatabase;

    private User $siswa;

    private TingkatSeleksi $tingkat;

    private Materi $materi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('RolesAndPermissionsSeeder');

        $this->siswa = User::factory()->create();
        $this->siswa->assignRole('siswa');

        $this->tingkat = TingkatSeleksi::factory()->create(['urutan' => 1]);
        $kompetensi = Kompetensi::factory()->create(['tingkat_id' => $this->tingkat->id]);
        $this->materi = Materi::factory()->create([
            'tingkat_id' => $this->tingkat->id,
            'kompetensi_id' => $kompetensi->id,
        ]);
    }

    /**
     * Putaran aktif dibuat lewat pretest yang sudah selesai (putaran berjalan
     * = pretest terakhir siswa di tingkat itu).
     */
    private function buatPutaranAktif(): Pretest
    {
        return Pretest::factory()->create([
            'user_id' => $this->siswa->id,
            'tingkat_id' => $this->tingkat->id,
            'nilai' => 60.0,
            'disubmit_pada' => now(),
            'selesai_pada' => now(),
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/materi')->assertUnauthorized();
    }

    public function test_tanpa_tingkat_diketahui_dibalas_422(): void
    {
        $this->actingAs($this->siswa)
            ->getJson('/api/materi')
            ->assertUnprocessable();
    }

    public function test_tingkat_terkunci_dibalas_403(): void
    {
        // Tingkat urutan dua terkunci selama tingkat pertama belum lulus.
        $this->tingkat->update(['urutan' => 2]);

        $this->actingAs($this->siswa)
            ->getJson('/api/materi?tingkat_id='.$this->tingkat->id)
            ->assertForbidden();
    }

    public function test_daftar_materi_menampilkan_progress_dan_nilai_latihan(): void
    {
        ProgressBelajar::create([
            'user_id' => $this->siswa->id,
            'materi_id' => $this->materi->id,
            'status' => 'selesai',
            'persentase' => 100,
            'tanggal_selesai' => now(),
        ]);

        $quiz = Quiz::create([
            'materi_id' => $this->materi->id,
            'nama_quiz' => 'Quiz '.$this->materi->judul,
            'jumlah_soal' => 10,
        ]);

        QuizPengerjaan::create([
            'user_id' => $this->siswa->id,
            'quiz_id' => $quiz->id,
            'nilai' => 80.0,
            'disubmit_pada' => now(),
            'selesai_pada' => now(),
        ]);

        $respons = $this->actingAs($this->siswa)
            ->getJson('/api/materi?tingkat_id='.$this->tingkat->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->materi->id)
            ->assertJsonPath('data.0.wajib', false)
            ->assertJsonPath('data.0.progress.status', 'selesai')
            ->assertJsonPath('data.0.progress.persentase', 100)
            ->assertJsonPath('data.0.latihan_belum_tersedia', false);

        $this->assertSame(80.0, (float) $respons->json('data.0.nilai_latihan_terbaik'));
    }

    public function test_detail_materi_memuat_isi(): void
    {
        $this->actingAs($this->siswa)
            ->getJson('/api/materi/'.$this->materi->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->materi->id)
            ->assertJsonPath('data.isi_materi', $this->materi->isi_materi);
    }

    public function test_tandai_selesai_mengisi_persentase_dan_tanggal(): void
    {
        $this->buatPutaranAktif();

        $this->actingAs($this->siswa)
            ->putJson('/api/materi/'.$this->materi->id.'/progress', ['status' => 'selesai'])
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.persentase', 100);

        $this->assertDatabaseHas('progress_belajar', [
            'user_id' => $this->siswa->id,
            'materi_id' => $this->materi->id,
            'status' => 'selesai',
            'persentase' => 100,
        ]);
    }

    public function test_progress_belajar_status_tidak_mengisi_tanggal_selesai(): void
    {
        $this->buatPutaranAktif();

        $this->actingAs($this->siswa)
            ->putJson('/api/materi/'.$this->materi->id.'/progress', ['status' => 'belajar'])
            ->assertOk()
            ->assertJsonPath('data.status', 'belajar')
            ->assertJsonPath('data.persentase', 0);

        $this->assertDatabaseHas('progress_belajar', [
            'user_id' => $this->siswa->id,
            'materi_id' => $this->materi->id,
            'status' => 'belajar',
        ]);
    }

    public function test_progress_tanpa_putaran_aktif_dibalas_409(): void
    {
        // Tanpa pretest sebelumnya, tidak ada putaran aktif.
        $response = $this->actingAs($this->siswa)
            ->putJson('/api/materi/'.$this->materi->id.'/progress', ['status' => 'selesai']);

        $response->assertStatus(409);
        $this->assertSame('BELUM_PRETEST', $response->json('kode'));
    }

    public function test_progress_status_selain_yang_diizinkan_dibalas_422(): void
    {
        $this->buatPutaranAktif();

        $this->actingAs($this->siswa)
            ->putJson('/api/materi/'.$this->materi->id.'/progress', ['status' => 'lompat'])
            ->assertUnprocessable();
    }
}
