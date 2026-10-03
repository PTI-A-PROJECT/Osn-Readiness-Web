<?php

use App\Http\Controllers\Api\Admin\AturanPemetaanController;
use App\Http\Controllers\Api\Admin\KompetensiController;
use App\Http\Controllers\Api\Admin\KonteksSoalController;
use App\Http\Controllers\Api\Admin\LatihanController;
use App\Http\Controllers\Api\Admin\MateriController;
use App\Http\Controllers\Api\Admin\PembahasanController;
use App\Http\Controllers\Api\Admin\SimulasiController;
use App\Http\Controllers\Api\Admin\SiswaController;
use App\Http\Controllers\Api\Admin\SoalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PretestController;
use App\Http\Controllers\Api\TingkatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute publik
|--------------------------------------------------------------------------
| Registrasi dan login tidak butuh token. Login dibatasi lima kali per menit
| per email + IP lewat named limiter "login" (lihat AppServiceProvider).
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
});

/*
|--------------------------------------------------------------------------
| Rute siswa
|--------------------------------------------------------------------------
| Semua rute di sini berada di balik auth:sanctum + active, sehingga akun yang
| dinonaktifkan di tengah sesi langsung tertolak.
|
| Blok per paket; hanya isi blok milik paket yang sedang dikerjakan supaya
| konflik merge antar orang tetap kecil.
*/

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // [B1-A] Akses tingkat dan status putaran.
    Route::get('tingkat', [TingkatController::class, 'index']);

    // [B2-A] Pre-test dan pemetaan.
    Route::post('pretest', [PretestController::class, 'store']);
    Route::get('pretest/{pretest}', [PretestController::class, 'show']);
    Route::put('pretest/{pretest}/jawaban', [PretestController::class, 'simpanJawaban']);
    Route::post('pretest/{pretest}/submit', [PretestController::class, 'submit']);

    // [B2-B] Materi, progress, latihan, dan syarat simulasi.
    // [B3-A] Simulasi, kelulusan, dan review.
    // [B3-B] Dashboard dan riwayat.

    // [B1-D + B1-E] Admin. Satu grup, satu lapisan: middleware role
    // Super Admin, lalu policy per endpoint di controller masing-masing.
    Route::prefix('admin')->middleware('role:Super Admin')->group(function (): void {
        // [B1-D] Struktur konten dan siswa
        // FQCN dipakai karena TingkatController tanpa awalan sudah dipakai
        // endpoint siswa GET /api/tingkat.
        Route::apiResource('tingkat', App\Http\Controllers\Api\Admin\TingkatController::class)->only(['index', 'show', 'update']);
        Route::apiResource('kompetensi', KompetensiController::class);
        Route::apiResource('materi', MateriController::class);
        Route::post('materi/{materi}/upload-image', [MateriController::class, 'uploadImage']);
        Route::apiResource('konteks-soal', KonteksSoalController::class);
        Route::apiResource('siswa', SiswaController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::post('siswa/{siswa}/deactivate', [SiswaController::class, 'deactivate']);

        // [B1-E] Soal, pembahasan, latihan, simulasi, aturan pemetaan
        Route::apiResource('soal', SoalController::class);
        Route::post('soal/{soal}/pembahasan', [PembahasanController::class, 'store']);
        Route::put('soal/{soal}/pembahasan', [PembahasanController::class, 'update']);
        Route::get('soal/{soal}/pembahasan', [PembahasanController::class, 'show']);
        Route::delete('soal/{soal}/pembahasan', [PembahasanController::class, 'destroy']);
        Route::apiResource('latihan', LatihanController::class);
        Route::apiResource('simulasi', SimulasiController::class);
        Route::get('tingkat/{tingkat}/aturan-pemetaan', [AturanPemetaanController::class, 'show']);
        Route::put('tingkat/{tingkat}/aturan-pemetaan', [AturanPemetaanController::class, 'update']);
    });
});
