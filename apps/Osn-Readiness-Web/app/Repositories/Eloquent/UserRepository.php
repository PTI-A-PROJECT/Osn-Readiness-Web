<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        // Tanpa membedakan huruf besar dan kecil; akun terhapus tidak ikut.
        return $this->model->newQuery()->whereRaw('lower(email) = ?', [strtolower($email)])->first();
    }

    public function findByGoogleSub(string $sub): ?User
    {
        // Query sempit: kecocokan unik index, akun terhapus otomatis
        // tidak ikut karena scope bawaan SoftDeletes.
        return $this->model->newQuery()->where('google_sub', $sub)->first();
    }

    public function lepasGoogleDariAkunTerhapus(string $sub): int
    {
        return $this->model->newQuery()->onlyTrashed()->where('google_sub', $sub)->update(['google_sub' => null]);
    }

    public function findByKodeReferal(string $kode): ?User
    {
        // Kode tersimpan uppercase (KodeReferal::normalisasi); akun terhapus
        // tidak ikut karena query lewat scope bawaan SoftDeletes.
        return $this->model->newQuery()->where('kode_referal', $kode)->first();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->with('roles')->latest()->paginate($perPage);
    }

    public function paginasiSiswa(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->role('siswa')->with('roles')->latest()->paginate($perPage);
    }

    public function cariSiswa(int $id): ?User
    {
        return $this->model->newQuery()->role('siswa')->find($id);
    }

    public function findByNim(string $nim): ?User
    {
        // Akun terhapus tidak ikut karena scope SoftDeletes.
        return $this->model->newQuery()->where('nim', $nim)->first();
    }

    public function paginasiAnggota(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->whereHas('roles', fn ($q) => $q->where('name', 'anggota'))
            ->with('roles')
            ->withCount('diundang')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function kodeReferalDipakai(string $kode, int $kecualiUserId): bool
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->where('kode_referal', $kode)
            ->where('id', '!=', $kecualiUserId)
            ->exists();
    }

    public function hitunganReferalAnggota(): Collection
    {
        return $this->model->newQuery()
            ->whereHas('roles', fn ($q) => $q->where('name', 'anggota'))
            ->withCount('diundang')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => (int) $user->diundang_count]);
    }
}
