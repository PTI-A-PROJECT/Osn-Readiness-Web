<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Halaman statis web. Controller ini ada supaya `php artisan route:cache`
 * bisa dijalankan: route dengan closure action tidak dapat di-cache.
 */
class HalamanStatisController extends Controller
{
    public function welcome(): View
    {
        return view('welcome');
    }

    public function dashboard(): View
    {
        return view('dashboard');
    }
}
