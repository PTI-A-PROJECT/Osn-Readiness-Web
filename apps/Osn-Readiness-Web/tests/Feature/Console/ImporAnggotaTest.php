<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImporAnggotaTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'anggota');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    public function test_impor_membuat_akun_dan_menggunakan_kata_berikutnya_saat_kode_kembar(): void
    {
        $path = $this->csv("nim,nama\n230533610158,ACHMAD ARIF SETIAWAN\n230533605308,ACHMAD IBRA ALFAHRO\n");

        $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(0);

        foreach ([
            ['230533610158', 'ACHMAD ARIF SETIAWAN', 'OSNACHMAD'],
            ['230533605308', 'ACHMAD IBRA ALFAHRO', 'OSNIBRA'],
        ] as [$nim, $nama, $kode]) {
            $user = User::where('nim', $nim)->firstOrFail();
            $this->assertSame($nama, $user->name);
            $this->assertSame($nim.'@anggota.invalid', $user->email);
            $this->assertTrue(Hash::check($nim, $user->password));
            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasRole('anggota'));
            $this->assertNotNull($user->referal_diisi_at);
            $this->assertSame($kode, $user->kode_referal);
        }
    }

    public function test_nama_yang_semua_katanya_terpakai_diberi_nomor_urut(): void
    {
        $path = $this->csv("nim,nama\n230533610158,BUDI\n230533605308,BUDI\n230533608093,BUDI\n");
        $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(0);

        $kode = fn (string $nim): string => User::where('nim', $nim)->value('kode_referal');

        $this->assertSame('OSNBUDI', $kode('230533610158'));
        $this->assertSame('OSNBUDI2', $kode('230533605308'));
        $this->assertSame('OSNBUDI3', $kode('230533608093'));
    }

    public function test_kode_akun_terhapus_tidak_dipakai_ulang(): void
    {
        User::factory()->create(['kode_referal' => 'OSNBUDI'])->delete();
        $path = $this->csv("nim,nama\n230533610158,BUDI\n");

        $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(0);

        $this->assertSame('OSNBUDI2', User::where('nim', '230533610158')->value('kode_referal'));
    }

    public function test_impor_ulang_melewati_akun_tanpa_mengubah_kredensial(): void
    {
        $path = $this->csv("nim,nama\n230533610158,BUDI\n");
        $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(0);
        $before = User::where('nim', '230533610158')->firstOrFail();
        $password = $before->password;
        $code = $before->kode_referal;

        $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(0);

        $this->assertSame(1, User::where('nim', '230533610158')->count());
        $this->assertSame($password, User::where('nim', '230533610158')->value('password'));
        $this->assertSame($code, User::where('nim', '230533610158')->value('kode_referal'));
    }

    public function test_impor_menolak_csv_tidak_valid_tanpa_membuat_akun(): void
    {
        foreach ([
            $this->csv("nim,nama\nabc,NAMA\n"),
            $this->csv("nomor,nama\n230533610158,NAMA\n"),
            $this->csv(''),
            $this->csv("nim,nama\n230533610158,SATU\n230533610158,DUA\n"),
            $this->csv("nim,nama\n230533610158,   \n"),
        ] as $path) {
            $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(1);
            $this->assertSame(0, User::count());
        }

        $this->artisan('anggota:impor', ['file' => sys_get_temp_dir().'/anggota-file-tidak-ada.csv'])->assertExitCode(1);
        $this->assertSame(0, User::count());
    }

    public function test_dry_run_tidak_membuat_akun(): void
    {
        $path = $this->csv("nim,nama\n230533610158,BUDI\n");
        $this->artisan('anggota:impor', ['file' => $path, '--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, User::count());
    }

    public function test_impor_membuat_role_anggota_jika_role_dihapus(): void
    {
        Role::findByName('anggota', 'web')->delete();
        $path = $this->csv("nim,nama\n230533610158,BUDI\n");

        $this->artisan('anggota:impor', ['file' => $path])->assertExitCode(0);

        $this->assertTrue(User::where('nim', '230533610158')->firstOrFail()->hasRole('anggota'));
    }
}
