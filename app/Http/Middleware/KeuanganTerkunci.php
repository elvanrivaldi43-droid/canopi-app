<?php
// FILE: app/Http/Middleware/KeuanganTerkunci.php
//
// Kunci Keuangan (spec 2026-09-30-kunci-keuangan-design.md). Dipakai lewat alias `keuangan`:
//   keuangan        -> halaman uang: belum ketik ulang password => ke halaman konfirmasi
//   keuangan:samar  -> halaman harian yang menampilkan angka: tak memblokir, view yang menyamarkan
// Kedua mode memberi `no-store` supaya tombol Back tak memunculkan halaman beruang dari cache.

namespace App\Http\Middleware;

use App\Services\KunciKeuangan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeuanganTerkunci
{
    public function handle(Request $request, Closure $next, string $mode = 'kunci'): Response
    {
        if ($mode !== 'samar' && !KunciKeuangan::terbukaSaatIni()) {
            return redirect()->guest(route('password.confirm'));
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
