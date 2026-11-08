<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TolakAnggota
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->roles->contains('name', 'anggota')) {
            return response()->json(['message' => 'Akun anggota tidak dapat memakai fitur belajar.'], 403);
        }

        return $next($request);
    }
}
