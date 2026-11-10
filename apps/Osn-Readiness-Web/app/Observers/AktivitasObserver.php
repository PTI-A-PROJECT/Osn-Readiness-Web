<?php

namespace App\Observers;

use App\Contracts\Services\AktivitasServiceInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mencatat perubahan konten oleh admin ke log_aktivitas. Hanya aktif bila ada
 * user terautentikasi, sehingga seeder dan perintah artisan tidak ikut tercatat.
 */
class AktivitasObserver
{
    private const ENTITAS = [
        'TingkatSeleksi' => 'tingkat',
        'MateriVideo' => 'materi_video',
        'KonteksSoal' => 'konteks_soal',
        'AturanPemetaan' => 'aturan_pemetaan',
        'Pembahasan' => 'pembahasan',
        'Kompetensi' => 'kompetensi',
        'Materi' => 'materi',
        'Soal' => 'soal',
        'Quiz' => 'latihan',
        'Simulasi' => 'simulasi',
    ];

    private const ATRIBUT_JUDUL = ['judul', 'nama_quiz', 'nama_simulasi', 'nama_tingkat', 'nama', 'kode'];

    public function __construct(
        private readonly AktivitasServiceInterface $aktivitas,
    ) {}

    public function created(Model $model): void
    {
        $this->catat($model, 'dibuat');
    }

    public function updated(Model $model): void
    {
        $this->catat($model, 'diubah', array_keys($model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        $this->catat($model, 'dihapus');
    }

    /**
     * @param  list<string>  $field
     */
    private function catat(Model $model, string $verba, array $field = []): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $entitas = self::ENTITAS[class_basename($model)]
            ?? Str::snake(class_basename($model));

        $meta = ['judul' => $this->judul($model)];

        $field = array_values(array_diff($field, ['updated_at']));

        if ($field !== []) {
            $meta['field'] = $field;
        }

        $this->aktivitas->catat(
            $user,
            $entitas.'.'.$verba,
            'admin',
            $meta,
            $model::class,
            (int) $model->getKey(),
        );
    }

    private function judul(Model $model): ?string
    {
        foreach (self::ATRIBUT_JUDUL as $atribut) {
            $nilai = $model->getAttribute($atribut);

            if (is_string($nilai) && $nilai !== '') {
                return Str::limit($nilai, 100);
            }
        }

        return null;
    }
}
