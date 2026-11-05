<?php

namespace Tests\Feature\Api;

use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Pretest;
use App\Models\ProgressBelajar;
use App\Models\Quiz;
use App\Models\QuizPengerjaan;
use App\Models\RekomendasiMateri;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'urutan' => 1,
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
            ->assertJsonPath('data.0.quiz_id', $quiz->id)
            ->assertJsonPath('data.0.quiz_nama', 'Quiz '.$this->materi->judul)
            ->assertJsonPath('data.0.quiz_jumlah_soal', 10)
            ->assertJsonPath('data.0.latihan_belum_tersedia', false);

        $this->assertSame(80.0, (float) $respons->json('data.0.nilai_latihan_terbaik'));
    }

    public function test_detail_dua_materi_mengirim_quiz_masing_masing(): void
    {
        $materiKedua = Materi::factory()->create([
            'tingkat_id' => $this->tingkat->id,
            'kompetensi_id' => $this->materi->kompetensi_id,
            'urutan' => 2,
        ]);

        $quizPertama = Quiz::create([
            'materi_id' => $this->materi->id,
            'nama_quiz' => 'Latihan Materi Pertama',
            'jumlah_soal' => 10,
        ]);
        $quizKedua = Quiz::create([
            'materi_id' => $materiKedua->id,
            'nama_quiz' => 'Latihan Materi Kedua',
            'jumlah_soal' => 15,
        ]);

        $this->actingAs($this->siswa)
            ->getJson('/api/materi/'.$this->materi->id)
            ->assertOk()
            ->assertJsonPath('data.quiz_id', $quizPertama->id)
            ->assertJsonPath('data.quiz_nama', 'Latihan Materi Pertama')
            ->assertJsonPath('data.quiz_jumlah_soal', 10);

        $this->getJson('/api/materi/'.$materiKedua->id)
            ->assertOk()
            ->assertJsonPath('data.quiz_id', $quizKedua->id)
            ->assertJsonPath('data.quiz_nama', 'Latihan Materi Kedua')
            ->assertJsonPath('data.quiz_jumlah_soal', 15);
    }

    public function test_materi_tanpa_latihan_mengirim_quiz_id_null(): void
    {
        $this->actingAs($this->siswa)
            ->getJson('/api/materi?tingkat_id='.$this->tingkat->id)
            ->assertOk()
            ->assertJsonPath('data.0.quiz_id', null)
            ->assertJsonPath('data.0.latihan_belum_tersedia', true);
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

    public function test_detail_materi_memecah_konten_menjadi_submateri(): void
    {
        $this->materi->update([
            'isi_materi' => "Pengantar singkat.\n\n## Konsep Dasar\nPenjelasan konsep.\n\n## Contoh Soal\nContoh dan pembahasan.",
        ]);

        $this->actingAs($this->siswa)
            ->getJson('/api/materi/'.$this->materi->id)
            ->assertOk()
            ->assertJsonPath('data.submateri.0.id', 'pendahuluan')
            ->assertJsonPath('data.submateri.1.id', 'konsep-dasar')
            ->assertJsonPath('data.submateri.1.judul', 'Konsep Dasar')
            ->assertJsonPath('data.submateri.1.selesai', false)
            ->assertJsonPath('data.submateri.2.id', 'contoh-soal');
    }

    public function test_progress_submateri_dihitung_dan_disimpan_per_bagian(): void
    {
        $this->buatPutaranAktif();
        $this->materi->update([
            'isi_materi' => "## Konsep Dasar\nPenjelasan konsep.\n\n## Contoh Soal\nContoh soal.",
        ]);

        $url = '/api/materi/'.$this->materi->id.'/submateri/';

        $this->actingAs($this->siswa)
            ->putJson($url.'konsep-dasar/selesai')
            ->assertOk()
            ->assertJsonPath('data.status', 'belajar')
            ->assertJsonPath('data.persentase', 50)
            ->assertJsonPath('data.submateri_selesai', 1)
            ->assertJsonPath('data.submateri.0.selesai', true)
            ->assertJsonPath('data.submateri.1.selesai', false);

        $this->assertDatabaseHas('progress_belajar', [
            'user_id' => $this->siswa->id,
            'materi_id' => $this->materi->id,
            'status' => 'belajar',
            'persentase' => 50,
        ]);
        $this->assertDatabaseHas('progress_submateri', [
            'kode' => 'konsep-dasar',
        ]);

        // Request berulang idempoten dan tidak membuat baris duplikat.
        $this->putJson($url.'konsep-dasar/selesai')->assertOk();
        $this->assertDatabaseCount('progress_submateri', 1);

        $this->putJson($url.'contoh-soal/selesai')
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.persentase', 100)
            ->assertJsonPath('data.submateri_selesai', 2);

        $this->assertDatabaseCount('progress_submateri', 2);
        $this->assertDatabaseHas('progress_belajar', [
            'user_id' => $this->siswa->id,
            'materi_id' => $this->materi->id,
            'status' => 'selesai',
            'persentase' => 100,
        ]);
    }

    public function test_submateri_tidak_dikenal_dibalas_404(): void
    {
        $this->buatPutaranAktif();
        $this->materi->update(['isi_materi' => "## Konsep Dasar\nIsi."]);

        $this->actingAs($this->siswa)
            ->putJson('/api/materi/'.$this->materi->id.'/submateri/tidak-ada/selesai')
            ->assertNotFound();
    }

    public function test_progress_submateri_tanpa_putaran_aktif_dibalas_409(): void
    {
        $this->materi->update(['isi_materi' => "## Konsep Dasar\nIsi."]);

        $response = $this->actingAs($this->siswa)
            ->putJson('/api/materi/'.$this->materi->id.'/submateri/konsep-dasar/selesai');

        $response->assertStatus(409);
        $this->assertSame('BELUM_PRETEST', $response->json('kode'));
    }

    public function test_materi_wajib_tampil_lebih_dulu_urut_prioritas(): void
    {
        $putaran = $this->buatPutaranAktif();

        $kompetensiId = $this->materi->kompetensi_id;
        $this->materi->update(['urutan' => 1]);

        $buat = fn (int $urutan): Materi => Materi::factory()->create([
            'tingkat_id' => $this->tingkat->id,
            'kompetensi_id' => $kompetensiId,
            'urutan' => $urutan,
        ]);

        $dua = $buat(2);
        $tiga = $buat(3);
        $empat = $buat(4);

        // Materi urutan 4 paling prioritas, lalu urutan 2. Urutan 1 dan 3
        // tidak wajib.
        foreach ([[$empat, 1], [$dua, 2]] as [$materi, $prioritas]) {
            RekomendasiMateri::create([
                'pretest_id' => $putaran->id,
                'user_id' => $this->siswa->id,
                'materi_id' => $materi->id,
                'prioritas' => $prioritas,
            ]);
        }

        $data = $this->actingAs($this->siswa)
            ->getJson('/api/materi?tingkat_id='.$this->tingkat->id)
            ->assertOk()
            ->json('data');

        $this->assertSame([$empat->id, $dua->id, $this->materi->id, $tiga->id], array_column($data, 'id'));
        $this->assertSame([true, true, false, false], array_column($data, 'wajib'));
    }

    public function test_detail_materi_tingkat_terkunci_dibalas_403(): void
    {
        $this->tingkat->update(['urutan' => 2]);

        $this->actingAs($this->siswa)
            ->getJson('/api/materi/'.$this->materi->id)
            ->assertForbidden()
            ->assertJsonPath('kode', 'TINGKAT_TERKUNCI');
    }

    public function test_daftar_materi_tidak_menambah_query_per_materi(): void
    {
        $this->buatPutaranAktif();

        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($this->siswa)
                ->getJson('/api/materi?tingkat_id='.$this->tingkat->id)
                ->assertOk();

            return count(DB::getQueryLog());
        };

        $sebelum = $hitung();

        foreach (range(2, 9) as $urutan) {
            $materi = Materi::factory()->create([
                'tingkat_id' => $this->tingkat->id,
                'kompetensi_id' => $this->materi->kompetensi_id,
                'urutan' => $urutan + 100,
            ]);

            Quiz::create(['materi_id' => $materi->id, 'nama_quiz' => 'Quiz '.$urutan, 'jumlah_soal' => 10]);
        }

        $this->assertSame($sebelum, $hitung(), 'Jumlah query tidak boleh bertambah seiring jumlah materi.');
    }
}
