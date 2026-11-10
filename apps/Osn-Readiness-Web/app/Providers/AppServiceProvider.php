<?php

namespace App\Providers;

use App\Models\AturanPemetaan;
use App\Models\Kompetensi;
use App\Models\KonteksSoal;
use App\Models\Materi;
use App\Models\MateriVideo;
use App\Models\Pembahasan;
use App\Models\Quiz;
use App\Models\Simulasi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use App\Observers\AktivitasObserver;
use App\Observers\TingkatSeleksiObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TingkatSeleksi::observe(TingkatSeleksiObserver::class);

        // Semua perubahan konten oleh admin dicatat ke log_aktivitas selama
        // ada user terautentikasi (seeder/artisan tidak ikut tercatat).
        foreach ([
            TingkatSeleksi::class,
            Kompetensi::class,
            Materi::class,
            MateriVideo::class,
            KonteksSoal::class,
            Soal::class,
            Pembahasan::class,
            Quiz::class,
            Simulasi::class,
            AturanPemetaan::class,
        ] as $model) {
            $model::observe(AktivitasObserver::class);
        }

        // Login dibatasi per identitas (NIM atau email) + IP, supaya satu akun
        // tidak bisa dibombardir dan satu IP tidak bisa mencoba banyak akun
        // sekaligus. NIM ikut dalam kunci: tanpanya semua login NIM dari satu
        // jaringan kampus berbagi satu jatah.
        RateLimiter::for('login', function (Request $request): Limit {
            $identitas = Str::lower((string) ($request->input('nim') ?: $request->input('email')));

            return Limit::perMinute(5)->by($identitas.'|'.$request->ip());
        });

        // Endpoint terautentikasi dibatasi per user, bukan per IP: satu jaringan
        // kampus berbagi IP sehingga batas per IP akan saling menghalangi.
        // 180/menit cukup untuk polling nilai tiap 5 detik (12/menit) ditambah
        // autosave 20 soal, tetapi FE yang rusak tidak bisa membanjiri server.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(180)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Registrasi menjalankan bcrypt yang mahal di CPU, jadi dibatasi per IP.
        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
