<?php

namespace App\Providers;

use App\Clients\FakePerhitunganClient;
use App\Contracts\Clients\PerhitunganClientInterface;
use App\Contracts\Randomizers\RandomizerInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AturanServiceInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\KelulusanServiceInterface;
use App\Contracts\Services\PenilaianServiceInterface;
use App\Contracts\Services\PutaranServiceInterface;
use App\Contracts\Services\SoalPickerServiceInterface;
use App\Contracts\Services\SyaratSimulasiServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Randomizers\SeededRandomizer;
use App\Repositories\Eloquent\UserRepository;
use App\Services\AturanService;
use App\Services\AuthService;
use App\Services\KelulusanService;
use App\Services\PenilaianService;
use App\Services\PutaranService;
use App\Services\SoalPickerService;
use App\Services\SyaratSimulasiService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ============================================================================
        // REPOSITORY BINDINGS
        // ============================================================================
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // ============================================================================
        // SERVICE BINDINGS
        // ============================================================================
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);

        // B0-B Services
        $this->app->bind(AturanServiceInterface::class, AturanService::class);
        $this->app->bind(PutaranServiceInterface::class, PutaranService::class);
        $this->app->bind(SoalPickerServiceInterface::class, SoalPickerService::class);
        $this->app->bind(SyaratSimulasiServiceInterface::class, SyaratSimulasiService::class);
        $this->app->bind(KelulusanServiceInterface::class, KelulusanService::class);
        $this->app->bind(PenilaianServiceInterface::class, PenilaianService::class);

        // ============================================================================
        // CLIENT BINDINGS
        // ============================================================================
        $this->app->bind(PerhitunganClientInterface::class, FakePerhitunganClient::class);

        // ============================================================================
        // RANDOMIZER BINDINGS
        // ============================================================================
        $this->app->singleton(RandomizerInterface::class, function ($app) {
            $seed = (int) env('RANDOMIZER_SEED', 42);

            return new SeededRandomizer($seed);
        });
    }
}
