<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AnggotaServiceInterface;
use App\Models\User;
use App\Support\KodeReferal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AnggotaService implements AnggotaServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->paginasiAnggota($perPage);
    }

    /**
     * @param  list<array{nim: string, nama: string}>  $baris
     * @return list<array{nim: string, nama: string, kode: ?string, status: 'dibuat'|'dilewati'}>
     */
    public function impor(array $baris, bool $dryRun = false): array
    {
        if (! $dryRun) {
            Role::findOrCreate('anggota', 'web');
        }

        return DB::transaction(function () use ($baris, $dryRun): array {
            $hasil = [];

            foreach ($baris as $barisAnggota) {
                $user = $this->userRepository->findByNim($barisAnggota['nim']);

                if ($user) {
                    $hasil[] = [
                        'nim' => $barisAnggota['nim'],
                        'nama' => $barisAnggota['nama'],
                        'kode' => $user->kode_referal,
                        'status' => 'dilewati',
                    ];

                    continue;
                }

                if ($dryRun) {
                    $hasil[] = [
                        'nim' => $barisAnggota['nim'],
                        'nama' => $barisAnggota['nama'],
                        'kode' => null,
                        'status' => 'dibuat',
                    ];

                    continue;
                }

                /** @var User $user */
                $user = $this->userRepository->create([
                    'name' => $barisAnggota['nama'],
                    'email' => $barisAnggota['nim'].'@anggota.invalid',
                    'password' => $barisAnggota['nim'],
                    'is_active' => true,
                    'nim' => $barisAnggota['nim'],
                    'kode_referal' => KodeReferal::untukAnggota($barisAnggota['nama']),
                    'referal_diisi_at' => now(),
                ]);
                $user->assignRole('anggota');

                $hasil[] = [
                    'nim' => $barisAnggota['nim'],
                    'nama' => $barisAnggota['nama'],
                    'kode' => $user->kode_referal,
                    'status' => 'dibuat',
                ];
            }

            return $hasil;
        });
    }

    public function ubahNamaKode(User $anggota, string $nama): User
    {
        $kodeBaru = KodeReferal::kodeAnggota($nama);

        if ($kodeBaru === (string) $anggota->kode_referal) {
            return $anggota->load('roles')->loadCount('diundang');
        }

        if ($this->userRepository->kodeReferalDipakai($kodeBaru, $anggota->id)) {
            throw ValidationException::withMessages([
                'nama' => ['Kode referal itu sudah dipakai. Coba nama lain.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($anggota, $kodeBaru): User {
                /** @var User $user */
                $user = $this->userRepository->update($anggota, ['kode_referal' => $kodeBaru]);

                return $user->load('roles')->loadCount('diundang');
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'nama' => ['Kode referal itu sudah dipakai. Coba nama lain.'],
            ]);
        }
    }

    /**
     * @return array{jumlah_referal: int, peringkat: int, jumlah_anggota: int, total_referal: int}
     */
    public function ringkasan(User $anggota): array
    {
        $hitungan = $this->userRepository->hitunganReferalAnggota();
        $saya = (int) ($hitungan[$anggota->id] ?? 0);

        return [
            'jumlah_referal' => $saya,
            'peringkat' => 1 + $hitungan->filter(fn (int $jumlah): bool => $jumlah > $saya)->count(),
            'jumlah_anggota' => $hitungan->count(),
            'total_referal' => (int) $hitungan->sum(),
        ];
    }
}
