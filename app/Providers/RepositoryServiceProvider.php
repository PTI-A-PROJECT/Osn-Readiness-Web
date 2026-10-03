<?php

namespace App\Providers;

use App\Clients\PerhitunganClient;
use App\Contracts\Clients\PerhitunganClientInterface;
use App\Contracts\Randomizers\RandomizerInterface;
use App\Contracts\Repositories\AturanPemetaanRepositoryInterface;
use App\Contracts\Repositories\HasilSimulasiRepositoryInterface;
use App\Contracts\Repositories\KenaikanTingkatRepositoryInterface;
use App\Contracts\Repositories\MateriRepositoryInterface;
use App\Contracts\Repositories\PretestRepositoryInterface;
use App\Contracts\Repositories\SoalRepositoryInterface;
use App\Contracts\Repositories\TingkatSeleksiRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AturanServiceInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\PutaranServiceInterface;
use App\Contracts\Services\SoalPickerServiceInterface;
use App\Contracts\Services\SyaratSimulasiServiceInterface;
use App\Contracts\Services\TingkatServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Randomizers\SeededRandomizer;
use App\Repositories\Eloquent\AturanPemetaanRepository;
use App\Repositories\Eloquent\HasilSimulasiRepository;
use App\Repositories\Eloquent\KenaikanTingkatRepository;
use App\Repositories\Eloquent\MateriRepository;
use App\Repositories\Eloquent\PretestRepository;
use App\Repositories\Eloquent\SoalRepository;
use App\Repositories\Eloquent\TingkatSeleksiRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\AturanService;
use App\Services\AuthService;
use App\Services\PutaranService;
use App\Services\SoalPickerService;
use App\Services\SyaratSimulasiService;
use App\Services\TingkatService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(TingkatSeleksiRepositoryInterface::class, TingkatSeleksiRepository::class);
        $this->app->bind(AturanPemetaanRepositoryInterface::class, AturanPemetaanRepository::class);
        $this->app->bind(PretestRepositoryInterface::class, PretestRepository::class);
        $this->app->bind(HasilSimulasiRepositoryInterface::class, HasilSimulasiRepository::class);
        $this->app->bind(KenaikanTingkatRepositoryInterface::class, KenaikanTingkatRepository::class);
        $this->app->bind(SoalRepositoryInterface::class, SoalRepository::class);
        $this->app->bind(MateriRepositoryInterface::class, MateriRepository::class);

        // Service bindings
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(TingkatServiceInterface::class, TingkatService::class);

        // Aturan dibaca berkali-kali dalam satu request; scoped menjaga cache per request.
        $this->app->scoped(AturanServiceInterface::class, AturanService::class);
        $this->app->bind(PutaranServiceInterface::class, PutaranService::class);
        $this->app->bind(SyaratSimulasiServiceInterface::class, SyaratSimulasiService::class);
        $this->app->bind(SoalPickerServiceInterface::class, SoalPickerService::class);

        // [B1-C] Klien layanan hitung Python. Dibaca dari config saat
        // instantiate supaya test bisa menyuntikkan stub.
        $this->app->bind(PerhitunganClientInterface::class, fn (): PerhitunganClientInterface => new PerhitunganClient(
            url: rtrim((string) config('services.perhitungan.url'), '/'),
            token: (string) config('services.perhitungan.token'),
            timeout: (int) config('services.perhitungan.timeout'),
            retry: (int) config('services.perhitungan.retry'),
        ));

        // Seed diambil dari env supaya pengacakan bisa diulang persis; test
        // sendiri menyuntikkan SeededRandomizer dengan seed pilihan.
        $this->app->bind(RandomizerInterface::class, function (): RandomizerInterface {
            return new SeededRandomizer((int) env('SOAL_ACAK_SEED', 20261003));
        });
    }
}
