<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLevel
{
    public function handle(Request $request, Closure $next, ...$levels): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $userLevel = auth()->user()->level;

        if (!in_array($userLevel, array_map('intval', $levels))) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk halaman ini.');
        }

        return $next($request);
    }
}