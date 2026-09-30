<?php
// FILE: app/Services/KunciKeuangan.php
//
// Kunci Keuangan (spec 2026-09-30-kunci-keuangan-design.md): data uang milik karyawan
// sendiri hanya terlihat setelah mengetik ulang password, berlaku DETIK detik (keputusan
// Elvan: 5 menit, dihitung sejak password diketik). Owner (level 1) bebas.
//
// Logika waktu/level MURNI (tanpa session/DB) supaya bisa dites; terbukaSaatIni() hanya
// menyuapi dia dari user + session. Penanda waktu memakai kunci bawaan Laravel
// (`auth.password_confirmed_at`, ditulis ConfirmablePasswordController).

namespace App\Services;

class KunciKeuangan
{
    const DETIK = 300;

    public static function terbuka(?int $level, $konfirmasiAt, ?int $now = null): bool
    {
        if ($level === null) return false; // tamu/level kosong: tak pernah terbuka
        if ($level === 1) return true;
        if (!is_numeric($konfirmasiAt)) return false;

        $umur = ($now ?? time()) - (int) $konfirmasiAt;
        return $umur >= 0 && $umur <= self::DETIK;
    }

    public static function terbukaSaatIni(): bool
    {
        $level = auth()->user()?->level;
        return self::terbuka($level === null ? null : (int) $level, session('auth.password_confirmed_at'));
    }

    /** Tujuan kembali setelah password benar: hanya path relatif satu-slash, selebihnya /dashboard. */
    public static function tujuanAman(?string $kembali): string
    {
        if ($kembali === null || $kembali === '') return '/dashboard';
        if ($kembali[0] !== '/') return '/dashboard';
        if (isset($kembali[1]) && ($kembali[1] === '/' || $kembali[1] === '\\')) return '/dashboard';
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $kembali)) return '/dashboard';
        return $kembali;
    }
}
