<?php

namespace App\Services\Admin;

use App\Contracts\Services\MateriServiceInterface;
use App\Exceptions\MateriMasihDigunakanException;
use App\Models\Materi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MateriService implements MateriServiceInterface
{
    /**
     * Get all materi with optional filters
     */
    public function getAll(?int $kompetensiId = null): Collection
    {
        $query = Materi::query();

        if ($kompetensiId) {
            $query->where('kompetensi_id', $kompetensiId);
        }

        return $query->get();
    }

    /**
     * Get materi by id
     */
    public function getById(int $id): Materi
    {
        return Materi::findOrFail($id);
    }

    /**
     * Create new materi
     */
    public function create(array $data): Materi
    {
        return Materi::create($data);
    }

    /**
     * Update materi
     */
    public function update(int $id, array $data): Materi
    {
        $materi = $this->getById($id);
        $materi->update($data);

        return $materi;
    }

    /**
     * Soft delete materi (throw if in use)
     *
     * @throws MateriMasihDigunakanException
     */
    public function delete(int $id): bool
    {
        $materi = $this->getById($id);

        // Check if materi has soal
        if (DB::table('soal')->where('materi_id', $materi->id)->exists()) {
            throw new MateriMasihDigunakanException('Materi masih memiliki soal');
        }

        // Check if materi has latihan
        if (DB::table('latihan')->where('materi_id', $materi->id)->exists()) {
            throw new MateriMasihDigunakanException('Materi masih memiliki latihan');
        }

        // Check if materi is referenced by hasil_simulasi
        if (DB::table('pemetaan_materi')->where('materi_id', $materi->id)->exists()) {
            throw new MateriMasihDigunakanException('Materi masih dirujuk hasil siswa');
        }

        return $materi->delete();
    }
}
