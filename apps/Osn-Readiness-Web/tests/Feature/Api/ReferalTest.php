<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_setiap_akun_baru_otomatis_punya_kode_referal(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Siswa Sendiri',
            'email' => 'pemilik@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $kode = $response->json('data.user.kode_referal');

        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}$/', $kode);
        $this->assertDatabaseHas('users', [
            'email' => 'pemilik@example.com',
            'kode_referal' => $kode,
        ]);
    }

    public function test_kode_valid_tercatat_sebagai_diundang(): void
    {
        $pemberi = User::factory()->create();

        $this->postJson('/api/auth/register', [
            'name' => 'Siswa Undangan',
            'email' => 'undangan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'kode_referal' => $pemberi->kode_referal,
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'undangan@example.com',
            'direferensikan_oleh' => $pemberi->id,
        ]);

        // Kode pengundang tidak boleh tersalin menjadi kode pendaftar.
        $this->assertDatabaseMissing('users', [
            'email' => 'undangan@example.com',
            'kode_referal' => $pemberi->kode_referal,
        ]);
    }

    public function test_kode_tidak_dikenal_ditolak_422(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Siswa Baru',
            'email' => 'baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'kode_referal' => 'ZZ99',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('kode_referal');
    }

    public function test_tanpa_kode_tidak_mengisi_pemberi(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Tanpa Undangan',
            'email' => 'sendiri@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'sendiri@example.com',
            'direferensikan_oleh' => null,
        ]);
    }

    public function test_kode_tidak_peduli_huruf_besar_dan_spasi(): void
    {
        $pemberi = User::factory()->create();
        $kode = $pemberi->kode_referal; // tersimpan uppercase

        $this->postJson('/api/auth/register', [
            'name' => 'Siswa Undangan',
            'email' => 'undangan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'kode_referal' => ' '.strtolower($kode).' ',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'undangan@example.com',
            'direferensikan_oleh' => $pemberi->id,
        ]);
    }

    public function test_kode_milik_akun_terhapus_tidak_bisa_dipakai(): void
    {
        $pemberi = User::factory()->create();
        $kode = $pemberi->kode_referal;
        $pemberi->delete();

        $this->postJson('/api/auth/register', [
            'name' => 'Siswa Undangan',
            'email' => 'undangan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'kode_referal' => $kode,
        ])->assertUnprocessable()->assertJsonValidationErrors('kode_referal');
    }

    public function test_kode_unik_antar_akun(): void
    {
        $satu = User::factory()->create();
        $dua = User::factory()->create();

        $this->assertNotSame($satu->kode_referal, $dua->kode_referal);
        $this->assertSame(4, strlen((string) $satu->kode_referal));
    }

    public function test_me_mengembalikan_kode_dan_jumlah_diundang(): void
    {
        $pemberi = User::factory()->create();
        User::factory()->count(2)->create(['direferensikan_oleh' => $pemberi->id]);

        $this->actingAs($pemberi)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.kode_referal', $pemberi->kode_referal)
            ->assertJsonPath('data.jumlah_diundang', 2);
    }

    public function test_akun_diundang_yang_dihapus_tidak_dihitung(): void
    {
        $pemberi = User::factory()->create();
        $diundang = User::factory()->create(['direferensikan_oleh' => $pemberi->id]);

        $this->assertSame(1, $pemberi->diundang()->count());

        $diundang->delete();

        $this->assertSame(0, $pemberi->diundang()->count());
    }

    public function test_kode_pengundang_tidak_tersalin_ke_akun_baru(): void
    {
        $pemberi = User::factory()->create();

        $respons = $this->postJson('/api/auth/register', [
            'name' => 'Siswa Undangan',
            'email' => 'undangan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'kode_referal' => $pemberi->kode_referal,
        ])->assertCreated();

        $baru = User::where('email', 'undangan@example.com')->first();

        $this->assertNotSame($pemberi->kode_referal, $baru->kode_referal);
        $this->assertSame(0, $respons->json('data.user.jumlah_diundang'));
    }
}
