<?php

namespace App\Providers;

use App\Models\TingkatSeleksi;
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

        // Login dibatasi per identitas (NIM atau email) + IP, supaya satu akun
        // tidak bisa dibombardir dan satu IP tidak bisa mencoba banyak akun
        // sekaligus. NIM ikut dalam kunci: tanpanya semua login NIM dari satu
        // jaringan kampus berbagi satu jatah.
        RateLimiter::for('login', function (Request $request): Limit {
            $identitas = Str::lower((string) ($request->input('nim') ?: $request->input('email')));

            return Limit::perMinute(5)->by($identitas.'|'.$request->ip());
        });
    }
}
