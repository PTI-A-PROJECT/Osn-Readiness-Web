<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// PUBLIC ROUTES (no auth required)
// ============================================================================

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
});

// ============================================================================
// PROTECTED ROUTES (requires auth:sanctum + active middleware)
// ============================================================================

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    // --------
    // AUTH
    // --------
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // --------
    // SISWA ROUTES
    // --------
    Route::middleware(['role:siswa'])->group(function (): void {
        // Tingkat Seleksi - readonly for siswa
        Route::get('tingkat-seleksi', [\App\Http\Controllers\Api\Siswa\TingkatSeleksiController::class, 'index']);
        Route::get('tingkat-seleksi/{tingkatSeleksi}', [\App\Http\Controllers\Api\Siswa\TingkatSeleksiController::class, 'show']);

        // Materi - readonly for siswa
        Route::get('materi', [\App\Http\Controllers\Api\Siswa\MateriController::class, 'index']);
        Route::get('materi/{materi}', [\App\Http\Controllers\Api\Siswa\MateriController::class, 'show']);

        // Soal - readonly with filtering
        Route::get('soal', [\App\Http\Controllers\Api\Siswa\SoalController::class, 'index']);
        Route::get('soal/{soal}', [\App\Http\Controllers\Api\Siswa\SoalController::class, 'show']);

        // Pretest
        Route::post('pretest/start', [\App\Http\Controllers\Api\Siswa\PretestController::class, 'start']);
        Route::get('pretest/{pretest}', [\App\Http\Controllers\Api\Siswa\PretestController::class, 'show']);
        Route::post('pretest/{pretest}/submit', [\App\Http\Controllers\Api\Siswa\PretestController::class, 'submit']);

        // Putaran - simulasi enrollment
        Route::get('putaran', [\App\Http\Controllers\Api\Siswa\PutaranController::class, 'index']);
        Route::post('putaran/{putaran}/enroll', [\App\Http\Controllers\Api\Siswa\PutaranController::class, 'enroll']);
        Route::get('putaran/{putaran}', [\App\Http\Controllers\Api\Siswa\PutaranController::class, 'show']);

        // Simulasi
        Route::post('simulasi/start', [\App\Http\Controllers\Api\Siswa\SimulasiController::class, 'start']);
        Route::get('simulasi/{simulasi}', [\App\Http\Controllers\Api\Siswa\SimulasiController::class, 'show']);
        Route::post('simulasi/{simulasi}/answer', [\App\Http\Controllers\Api\Siswa\SimulasiController::class, 'answer']);
        Route::post('simulasi/{simulasi}/submit', [\App\Http\Controllers\Api\Siswa\SimulasiController::class, 'submit']);
        Route::get('simulasi/{simulasi}/hasil', [\App\Http\Controllers\Api\Siswa\SimulasiController::class, 'hasil']);

        // Penilaian & Hasil
        Route::get('hasil', [\App\Http\Controllers\Api\Siswa\HasilController::class, 'index']);
        Route::get('hasil/{hasil}', [\App\Http\Controllers\Api\Siswa\HasilController::class, 'show']);
    });

    // --------
    // ADMIN ROUTES
    // --------
    Route::middleware(['role:admin'])->group(function (): void {
        // Users management
        Route::apiResource('users', UserController::class);

        // Tingkat Seleksi
        Route::apiResource('tingkat-seleksi', [\App\Http\Controllers\Api\Admin\TingkatSeleksiController::class]);

        // Materi
        Route::apiResource('materi', [\App\Http\Controllers\Api\Admin\MateriController::class]);

        // Soal
        Route::apiResource('soal', [\App\Http\Controllers\Api\Admin\SoalController::class]);

        // Pretest
        Route::apiResource('pretest', [\App\Http\Controllers\Api\Admin\PretestController::class]);

        // Putaran
        Route::apiResource('putaran', [\App\Http\Controllers\Api\Admin\PutaranController::class]);

        // Simulasi
        Route::apiResource('simulasi', [\App\Http\Controllers\Api\Admin\SimulasiController::class]);

        // Penilaian & Hasil
        Route::apiResource('hasil', [\App\Http\Controllers\Api\Admin\HasilController::class]);
        Route::post('hasil/{hasil}/rekalkulasi', [\App\Http\Controllers\Api\Admin\HasilController::class, 'rekalkulasi']);

        // Kelas
        Route::apiResource('kelas', [\App\Http\Controllers\Api\Admin\KelasController::class]);

        // Kurikulum
        Route::apiResource('kurikulum', [\App\Http\Controllers\Api\Admin\KurikulumController::class]);
    });
});
