<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\GoogleLoginCodeRepositoryInterface;
use App\Models\GoogleLoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleLoginCodeRepository implements GoogleLoginCodeRepositoryInterface
{
    public function __construct(
        private readonly GoogleLoginCode $model,
    ) {}

    public function buat(User $user): string
    {
        $kode = Str::random(64);

        // Bersihkan sisa baris lama di sini saja (bukan per request) supaya
        // tabel tetap kecil tanpa memerlukan penjadwal terpisah sebelum
        // pekerjaan cron tersedia.
        $this->model->newQuery()
            ->whereNotNull('dipakai_pada')
            ->orWhere('kedaluwarsa_pada', '<=', now())
            ->delete();

        $this->model->newQuery()->create([
            'user_id' => $user->id,
            'kode_hash' => hash('sha256', $kode),
            'kedaluwarsa_pada' => now()->addSeconds(self::umurDetik()),
        ]);

        return $kode;
    }

    public function konsumsi(string $kodeHash): ?GoogleLoginCode
    {
        return DB::transaction(function () use ($kodeHash): ?GoogleLoginCode {
            $baris = $this->model->newQuery()
                ->where('kode_hash', $kodeHash)
                ->lockForUpdate()
                ->first();

            if (! $baris || $baris->dipakai_pada || $baris->kedaluwarsa_pada->lte(now())) {
                return null;
            }

            // Pembaruan kondisional sebagai jaring pengaman: bila dua
            // request lolos pemeriksaan di atas, hanya satu yang benar-benar
            // berhasil menetapkan dipakai_pada.
            $diperbarui = $this->model->newQuery()
                ->where('kode_hash', $kodeHash)
                ->whereNull('dipakai_pada')
                ->update(['dipakai_pada' => now()]);

            if ($diperbarui === 0) {
                return null;
            }

            return $baris->refresh();
        });
    }

    /** Umur maksimum kode dari awal sampai kedaluwarsa, saat ini 60 detik. */
    public static function umurDetik(): int
    {
        return 60;
    }
}
