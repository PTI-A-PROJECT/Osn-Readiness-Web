<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Google OAuth (login siswa)
|--------------------------------------------------------------------------
| Route web, bukan API: verifikasi `state` bergantung pada sesi yang
| disimpan Google antara redirect dan callback. Tidak diharuskan login,
| karena alur menautkan Google dari profil siswa juga melewati sini.
*/

Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('google.callback');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
