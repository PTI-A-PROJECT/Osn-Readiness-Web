<?php

namespace App\Observers;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;

class TingkatSeleksiObserver
{
    /**
     * Handle the TingkatSeleksi "created" event.
     */
    public function created(TingkatSeleksi $tingkatSeleksi): void
    {
        // Skip during seeding - let seeders handle aturan creation
        if ($this->isSeeding()) {
            return;
        }

        // Auto-create default aturan for new tingkat if not already exists
        \DB::transaction(function () use ($tingkatSeleksi) {
            if (! AturanPemetaan::where('tingkat_id', $tingkatSeleksi->id)->exists()) {
                AturanPemetaan::create([
                    'tingkat_id' => $tingkatSeleksi->id,
                    'bobot_pretest' => 30,
                    'persen_pretest_mudah' => 50,
                    'persen_pretest_sedang' => 30,
                    'persen_pretest_sulit' => 20,
                    'passing_grade_pretest' => 60,
                    'bobot_simulasi' => 70,
                    'persen_simulasi_mudah' => 30,
                    'persen_simulasi_sedang' => 40,
                    'persen_simulasi_sulit' => 30,
                    'passing_grade_simulasi' => 70,
                    'latihan_min_nilai' => 50,
                    'pretest_jumlah_soal' => 30,
                    'pretest_min_soal_per_materi' => 2,
                    'simulasi_maks_percobaan' => 3,
                    'jumlah_materi_wajib' => 3,
                ]);
            }
        });
    }

    /**
     * Check if we're currently seeding the database.
     */
    private function isSeeding(): bool
    {
        // During seeding, check if AturanPemetaanSeeder is in the call stack
        return in_array('Database\Seeders\AturanPemetaanSeeder',
            array_map(fn ($trace) => $trace['class'] ?? '', debug_backtrace()),
            true);
    }

    /**
     * Handle the TingkatSeleksi "updated" event.
     */
    public function updated(TingkatSeleksi $tingkatSeleksi): void
    {
        //
    }

    /**
     * Handle the TingkatSeleksi "deleted" event.
     */
    public function deleted(TingkatSeleksi $tingkatSeleksi): void
    {
        //
    }

    /**
     * Handle the TingkatSeleksi "restored" event.
     */
    public function restored(TingkatSeleksi $tingkatSeleksi): void
    {
        //
    }

    /**
     * Handle the TingkatSeleksi "force deleted" event.
     */
    public function forceDeleted(TingkatSeleksi $tingkatSeleksi): void
    {
        //
    }
}
