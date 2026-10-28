<?php

namespace Tests\Feature\Api;

use App\Models\GoogleLoginCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferalSetelahGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function akunGoogleBaru(): User
    {
        // Akun yang baru dibuat callback Google: referal_diisi_at masih null.
        $user = User::factory()->create(['referal_diisi_at' => null]);
        $user->assignRole('siswa');

        return $user;
    }

    public function test_akun_google_baru_dapat_mengisi_kode_referal_sekali(): void
    {
        $pemberi = User::factory()->create();
        $pemberi->assignRole('siswa');
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => $pemberi->kode_referal])
            ->assertOk()
            ->assertJsonPath('message', 'Kode referal berhasil disimpan');

        $baru->refresh();
        $this->assertSame($pemberi->id, $baru->direferensikan_oleh);
        $this->assertNotNull($baru->referal_diisi_at);
    }

    public function test_kode_dibesarkan_dan_dirapikan_seperti_register_biasa(): void
    {
        $pemberi = User::factory()->create();
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => '  '.strtolower($pemberi->kode_referal).' '])
            ->assertOk();

        $this->assertSame($pemberi->id, $baru->fresh()->direferensikan_oleh);
    }

    public function test_akun_google_baru_bisa_melewati_tanpa_kode(): void
    {
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => ''])
            ->assertOk()
            ->assertJsonPath('message', 'Kode referal dilewati');

        $baru->refresh();
        $this->assertNull($baru->direferensikan_oleh);
        $this->assertNotNull($baru->referal_diisi_at);
    }

    public function test_setelah_diisi_tidak_bisa_diisi_lagi(): void
    {
        $pemberi = User::factory()->create();
        $pemberiKedua = User::factory()->create();
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => $pemberi->kode_referal])->assertOk();

        $this->postJson('/api/auth/referal', ['kode_referal' => $pemberiKedua->kode_referal])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kode_referal');

        $this->assertSame($pemberi->id, $baru->fresh()->direferensikan_oleh);
    }

    public function test_setelah_dilewati_tidak_bisa_diisi_lagi(): void
    {
        $pemberi = User::factory()->create();
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => null])->assertOk();

        $this->postJson('/api/auth/referal', ['kode_referal' => $pemberi->kode_referal])
            ->assertUnprocessable();

        $this->assertNull($baru->fresh()->direferensikan_oleh);
    }

    public function test_akun_lama_yang_sudah_ada_tidak_bisa_mengisi(): void
    {
        // Akun yang sudah ada sebelum fitur (dibackfill) tidak boleh diberi opsi.
        $pemberi = User::factory()->create();
        $lama = User::factory()->create(['referal_diisi_at' => now()]);
        $lama->assignRole('siswa');
        Sanctum::actingAs($lama);

        $this->postJson('/api/auth/referal', ['kode_referal' => $pemberi->kode_referal])
            ->assertUnprocessable();

        $this->assertNull($lama->fresh()->direferensikan_oleh);
    }

    public function test_kode_tidak_dikenal_ditolak_dan_akun_tetap_berhak_mengisi(): void
    {
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => 'TIDAKADA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kode_referal');

        $this->assertNull($baru->fresh()->referal_diisi_at);
    }

    public function test_tidak_bisa_memakai_kode_milik_sendiri(): void
    {
        $baru = $this->akunGoogleBaru();
        Sanctum::actingAs($baru);

        $this->postJson('/api/auth/referal', ['kode_referal' => $baru->kode_referal])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kode_referal');

        $this->assertNull($baru->fresh()->direferensikan_oleh);
    }

    public function test_respons_exchange_google_menandai_akun_baru_perlu_kode(): void
    {
        $baru = $this->akunGoogleBaru();
        $kodeLogin = GoogleLoginCode::query()->create([
            'user_id' => $baru->id,
            'kode_hash' => hash('sha256', 'kode-uji'),
            'kedaluwarsa_pada' => now()->addMinute(),
        ]);

        $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-uji'])
            ->assertOk()
            ->assertJsonPath('data.perlu_kode_referal', true);
    }

    public function test_respons_exchange_akun_lama_tidak_menandai_perlu_kode(): void
    {
        $lama = User::factory()->create(['referal_diisi_at' => now()]);
        $lama->assignRole('siswa');
        GoogleLoginCode::query()->create([
            'user_id' => $lama->id,
            'kode_hash' => hash('sha256', 'kode-lama'),
            'kedaluwarsa_pada' => now()->addMinute(),
        ]);

        $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-lama'])
            ->assertOk()
            ->assertJsonPath('data.perlu_kode_referal', false);
    }
}
