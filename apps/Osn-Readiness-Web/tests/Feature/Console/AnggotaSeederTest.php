<?php

namespace Tests\Feature\Console;

use App\Contracts\Services\AnggotaServiceInterface;
use App\Models\User;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AnggotaSeederTest extends TestCase
{
    use RefreshDatabase;

    private function jalankan(array $data): void
    {
        (new AnggotaSeeder)->jalankan(app(AnggotaServiceInterface::class), $data);
    }

    public function test_seeder_membuat_anggota_dan_aman_dijalankan_ulang(): void
    {
        $data = [
            ['230533000001', 'ACHMAD ARIF SETIAWAN'],
            ['230533000002', 'ACHMAD  IBRA ALFAHRO'],
        ];

        $this->jalankan($data);
        $this->jalankan($data);

        $this->assertSame(2, User::whereNotNull('nim')->count());
        $this->assertSame('OSNACHMAD', User::where('nim', '230533000001')->value('kode_referal'));
        $this->assertSame('OSNIBRA', User::where('nim', '230533000002')->value('kode_referal'));
        $this->assertTrue(User::where('nim', '230533000001')->first()->hasRole('anggota'));
    }

    public function test_seeder_menolak_data_tidak_valid_tanpa_membuat_akun(): void
    {
        try {
            $this->jalankan([['230533000001', 'BUDI'], ['abc', 'ANI']]);
            $this->fail('Seharusnya menolak NIM tidak valid.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, User::whereNotNull('nim')->count());
        }
    }
}
