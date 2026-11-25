<?php

namespace Tests\Feature\Api\Admin;

use App\Models\HasilSimulasi;
use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Pretest;
use App\Models\Quiz;
use App\Models\QuizPengerjaan;
use App\Models\Simulasi;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $siswa;

    private TingkatSeleksi $tingkat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->siswa = User::factory()->create();
        $this->siswa->assignRole('siswa');

        $this->tingkat = TingkatSeleksi::factory()->create(['urutan' => 1]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_siswa_dilarang_melihat_dashboard(): void
    {
        $this->actingAs($this->siswa)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_ringkasan_menghitung_angka_dengan_benar(): void
    {
        // Dua siswa aktif; satu mengarahkan tingkat aktifnya ke tingkat ini.
        $siswaDua = User::factory()->create(['tingkat_aktif_id' => $this->tingkat->id]);
        $siswaDua->assignRole('siswa');

        $this->siswa->update(['tingkat_aktif_id' => $this->tingkat->id]);

        // Satu siswa nonaktif tidak ikut dihitung.
        $nonaktif = User::factory()->create(['is_active' => false]);
        $nonaktif->assignRole('siswa');

        Pretest::create([
            'user_id' => $this->siswa->id,
            'tingkat_id' => $this->tingkat->id,
            'nilai' => 80.0,
            'disubmit_pada' => now(),
            'selesai_pada' => now(),
        ]);

        Pretest::create([
            'user_id' => $this->siswa->id,
            'tingkat_id' => $this->tingkat->id,
            'nilai' => 60.0,
            'disubmit_pada' => now(),
            'selesai_pada' => now(),
        ]);

        $data = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['siswa_aktif']);
        $this->assertSame(2, $data['pengerjaan_per_jenis']['pretest']);
        $this->assertSame(0, $data['pengerjaan_per_jenis']['latihan']);
        $this->assertSame(0, $data['pengerjaan_per_jenis']['simulasi']);
        $this->assertSame(70.0, (float) $data['rata_rata_nilai_per_jenis']['pretest']);

        $kabupaten = collect($data['siswa_per_tingkat_aktif'])
            ->firstWhere('tingkat_id', $this->tingkat->id);

        $this->assertSame(2, $kabupaten['jumlah_siswa']);
    }

    public function test_analitik_pengguna_menghitung_user_unik_siswa_dan_kontrak_lama_utuh(): void
    {
        $siswaDua = User::factory()->create(['is_active' => false]);
        $siswaDua->assignRole('siswa');

        $terhapus = User::factory()->create();
        $terhapus->assignRole('siswa');

        // Satu siswa dengan percobaan ganda: hanya dihitung sekali per jenis.
        $this->mulaiSemuaJenis($this->siswa, pretest: 3, latihan: 2, simulasi: 2);
        $this->mulaiSemuaJenis($siswaDua, pretest: 1, latihan: 0, simulasi: 1);
        $this->mulaiSemuaJenis($terhapus, pretest: 1, latihan: 1, simulasi: 1);
        // Admin yang kebetulan punya pretest tidak dihitung sebagai siswa.
        $this->mulaiSemuaJenis($this->admin, pretest: 1, latihan: 0, simulasi: 0);
        $terhapus->delete();

        $data = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertSame(
            ['siswa_aktif', 'pengerjaan_per_jenis', 'siswa_per_tingkat_aktif', 'rata_rata_nilai_per_jenis', 'analitik_pengguna'],
            array_keys($data),
        );
        $this->assertSame(1, $data['siswa_aktif']);
        $this->assertSame([
            'siswa_terdaftar' => 2,
            'mulai_pretest' => 2,
            'mulai_latihan' => 1,
            'mulai_simulasi' => 2,
            'selesai_pretest' => 2,
            'selesai_latihan' => 1,
            'selesai_simulasi' => 2,
            'terdaftar_dengan_referal' => 0,
        ], $data['analitik_pengguna']);
    }

    public function test_terdaftar_dengan_referal_menghitung_pemberi_terhapus_dan_mengecualikan_siswa_terhapus(): void
    {
        $pemberiTerhapus = User::factory()->create();
        $pemberiTerhapus->assignRole('siswa');

        $this->diundang($this->siswa);
        $this->diundang($this->siswa);
        $this->diundang($pemberiTerhapus);
        $pemberiTerhapus->delete();

        User::factory()->create()->assignRole('siswa');

        // Siswa yang sudah dihapus tidak dihitung.
        $this->diundang($this->siswa)->delete();
        // Akun admin yang kebetulan punya pemberi tidak dihitung sebagai siswa.
        $this->admin->update(['direferensikan_oleh' => $this->siswa->id]);

        $analitik = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->json('data.analitik_pengguna');

        // Pemberi yang di-soft-delete tetap dihitung: relasi direferensikan_oleh masih ada.
        $this->assertSame(3, $analitik['terdaftar_dengan_referal']);
        $this->assertSame(5, $analitik['siswa_terdaftar']);
    }

    public function test_filter_tanggal_memakai_batas_hari_inklusif(): void
    {
        $this->siswaPada('2020-02-29 23:59:59');
        $this->siswaPada('2020-03-01 00:00:00');
        $this->siswaPada('2020-03-31 23:59:59');
        $this->siswaPada('2020-04-01 00:00:00');

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')
            ->assertOk()
            ->assertJsonPath('data.analitik_pengguna.siswa_terdaftar', 2);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-31&sampai=2020-03-31')
            ->assertOk()
            ->assertJsonPath('data.analitik_pengguna.siswa_terdaftar', 1);
    }

    public function test_filter_dari_atau_sampai_saja_bersifat_terbuka(): void
    {
        $this->siswaPada('2020-02-29 23:59:59');
        $this->siswaPada('2020-03-31 23:59:59');
        $this->siswaPada('2020-04-01 00:00:00');

        // Hanya sampai: semua akun terdaftar sampai akhir hari itu.
        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?sampai=2020-03-31')
            ->assertOk()
            ->assertJsonPath('data.analitik_pengguna.siswa_terdaftar', 2);

        // Hanya dari: siswa dari setUp (dibuat sekarang) ikut terhitung.
        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-31')
            ->assertOk()
            ->assertJsonPath('data.analitik_pengguna.siswa_terdaftar', 3);
    }

    public function test_mulai_dan_selesai_memakai_kolom_waktu_masing_masing(): void
    {
        $belumSubmit = $this->siswaPada('2019-12-01 00:00:00');
        $sudahSubmit = $this->siswaPada('2019-12-01 00:00:00');
        $latihan = $this->siswaPada('2019-12-01 00:00:00');
        $simulasi = $this->siswaPada('2019-12-01 00:00:00');

        // Mulai di periode, belum disubmit.
        Pretest::factory()->create([
            'user_id' => $belumSubmit->id,
            'tingkat_id' => $this->tingkat->id,
            'created_at' => '2020-03-15 10:00:00',
        ]);

        // Mulai sebelum periode, disubmit di periode.
        Pretest::factory()->create([
            'user_id' => $sudahSubmit->id,
            'tingkat_id' => $this->tingkat->id,
            'created_at' => '2020-01-10 10:00:00',
            'disubmit_pada' => '2020-03-15 11:00:00',
        ]);

        QuizPengerjaan::factory()->create([
            'user_id' => $latihan->id,
            'quiz_id' => $this->buatQuiz()->id,
            'created_at' => '2020-03-05 08:00:00',
            'disubmit_pada' => '2020-03-05 09:00:00',
        ]);

        // Simulasi dimulai di periode lewat mulai_pada, tapi belum disubmit.
        $pretestSimulasi = Pretest::factory()->create([
            'user_id' => $simulasi->id,
            'tingkat_id' => $this->tingkat->id,
            'created_at' => '2019-06-01 00:00:00',
        ]);
        HasilSimulasi::factory()->create([
            'user_id' => $simulasi->id,
            'pretest_id' => $pretestSimulasi->id,
            'simulasi_id' => Simulasi::factory()->create(['tingkat_id' => $this->tingkat->id])->id,
            'mulai_pada' => '2020-03-20 10:00:00',
        ]);

        $analitik = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')
            ->assertOk()
            ->json('data.analitik_pengguna');

        $this->assertSame([
            'siswa_terdaftar' => 0,
            'mulai_pretest' => 1,
            'mulai_latihan' => 1,
            'mulai_simulasi' => 1,
            'selesai_pretest' => 1,
            'selesai_latihan' => 1,
            'selesai_simulasi' => 0,
            'terdaftar_dengan_referal' => 0,
        ], $analitik);

        $seluruhWaktu = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->json('data.analitik_pengguna');
        $this->assertSame(1, $seluruhWaktu['selesai_pretest']);
        $this->assertSame(1, $seluruhWaktu['selesai_latihan']);
        $this->assertSame(0, $seluruhWaktu['selesai_simulasi']);
    }

    public function test_percobaan_ganda_dihitung_satu_siswa_unik(): void
    {
        $siswa = $this->siswaPada('2019-12-01 00:00:00');

        foreach (['2020-03-01 08:00:00', '2020-03-10 08:00:00', '2020-03-31 23:59:59'] as $waktu) {
            Pretest::factory()->selesai()->create([
                'user_id' => $siswa->id,
                'tingkat_id' => $this->tingkat->id,
                'created_at' => $waktu,
                'disubmit_pada' => $waktu,
            ]);
        }

        Pretest::factory()->create([
            'user_id' => $siswa->id,
            'tingkat_id' => $this->tingkat->id,
            'created_at' => '2020-03-02 08:00:00',
        ]);

        $analitik = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')
            ->assertOk()
            ->json('data.analitik_pengguna');

        $this->assertSame(1, $analitik['mulai_pretest']);
        $this->assertSame(1, $analitik['selesai_pretest']);
    }

    public function test_tanpa_filter_tetap_seluruh_waktu_dan_metrik_lama_tidak_terfilter(): void
    {
        $this->siswaPada('2020-03-15 00:00:00');
        $this->mulaiSemuaJenis($this->siswa, pretest: 1, latihan: 1, simulasi: 1);

        $tanpaFilter = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->json('data');

        $denganFilter = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $tanpaFilter['analitik_pengguna']['siswa_terdaftar']);
        $this->assertSame(1, $tanpaFilter['analitik_pengguna']['mulai_pretest']);
        $this->assertSame(1, $denganFilter['analitik_pengguna']['siswa_terdaftar']);
        $this->assertSame(0, $denganFilter['analitik_pengguna']['mulai_pretest']);

        foreach (['siswa_aktif', 'pengerjaan_per_jenis', 'siswa_per_tingkat_aktif', 'rata_rata_nilai_per_jenis'] as $kunci) {
            $this->assertSame($tanpaFilter[$kunci], $denganFilter[$kunci]);
        }
        $this->assertSame(1, $denganFilter['pengerjaan_per_jenis']['pretest']);
    }

    public function test_terdaftar_dengan_referal_mengikuti_tanggal_daftar_akun(): void
    {
        $this->siswaPada('2020-03-10 09:00:00', ['direferensikan_oleh' => $this->siswa->id]);
        $this->siswaPada('2020-03-12 09:00:00', ['direferensikan_oleh' => $this->siswa->id, 'is_active' => false]);
        $this->siswaPada('2019-03-10 09:00:00', ['direferensikan_oleh' => $this->siswa->id]);
        $this->siswaPada('2020-03-13 09:00:00', ['direferensikan_oleh' => $this->siswa->id])->delete();

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')
            ->assertOk()
            ->assertJsonPath('data.analitik_pengguna.terdaftar_dengan_referal', 2);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.analitik_pengguna.terdaftar_dengan_referal', 3);
    }

    public function test_filter_tanggal_dibatasi_admin_dan_tanggal_tidak_valid_ditolak(): void
    {
        $this->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')->assertUnauthorized();

        $this->actingAs($this->siswa)
            ->getJson('/api/admin/dashboard?dari=2020-03-01&sampai=2020-03-31')
            ->assertForbidden();

        foreach (['2020-13-01', '01-03-2020', '2020-02-30', '2020-3-1', 'kemarin'] as $tanggal) {
            $this->actingAs($this->admin)
                ->getJson("/api/admin/dashboard?dari=$tanggal")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['dari']);
        }

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?sampai=2020-02-30')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sampai']);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-31&sampai=2020-03-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sampai']);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?dari=2020-03-31&sampai=2020-03-31')
            ->assertOk();
    }

    private function siswaPada(string $waktu, array $atribut = []): User
    {
        $user = User::factory()->create(['created_at' => $waktu] + $atribut);
        $user->assignRole('siswa');

        return $user;
    }

    private function mulaiSemuaJenis(User $user, int $pretest, int $latihan, int $simulasi): void
    {
        for ($i = 0; $i < $pretest; $i++) {
            Pretest::factory()->selesai()->create(['user_id' => $user->id, 'tingkat_id' => $this->tingkat->id]);
        }

        $pretestId = Pretest::where('user_id', $user->id)->value('id');
        $quizId = $this->buatQuiz()->id;

        for ($i = 0; $i < $latihan; $i++) {
            QuizPengerjaan::factory()->selesai()->create(['user_id' => $user->id, 'quiz_id' => $quizId]);
        }

        for ($i = 0; $i < $simulasi; $i++) {
            HasilSimulasi::factory()->selesai()->create([
                'user_id' => $user->id,
                'pretest_id' => $pretestId,
                'simulasi_id' => Simulasi::factory()->create(['tingkat_id' => $this->tingkat->id])->id,
            ]);
        }
    }

    private function buatQuiz(): Quiz
    {
        $materi = Materi::factory()->create([
            'tingkat_id' => $this->tingkat->id,
            'kompetensi_id' => Kompetensi::factory()->create(['tingkat_id' => $this->tingkat->id])->id,
        ]);

        return Quiz::factory()->create(['materi_id' => $materi->id]);
    }

    private function diundang(User $pemberi): User
    {
        $user = User::factory()->create(['direferensikan_oleh' => $pemberi->id]);
        $user->assignRole('siswa');

        return $user;
    }
}
