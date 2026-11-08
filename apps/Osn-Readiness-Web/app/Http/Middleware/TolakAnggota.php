<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TolakAnggota
{
    public function handle(Request $request, Closure $next): Response
    {
        // loadMissing: kalau relasi sudah dimuat (mis. oleh controller), tidak
        // ada query ulang dan pemuatan sebelumnya tidak tertimpa.
        if ($request->user()?->loadMissing('roles')->roles->contains('name', 'anggota')) {
            return response()->json(['message' => 'Akun anggota tidak dapat memakai fitur belajar.'], 403);
        }

        return $next($request);
    }
}
