<?php
// FILE: app/Services/CronAuth.php
//
// Kunci pemicu cron (public/cron-*.php). Dulu tertulis di kode — repo GitHub PUBLIK, jadi
// siapa pun bisa memicu cron. Sekarang kunci ada di .env server (CRON_KEY), dicek di sini.
// Gagal TERTUTUP: kunci server kosong/kurang dari 16 karakter = semua ditolak (tak pernah
// "kosong cocok dengan kosong"). getenv() dipakai (lebih andal dari env() di shared hosting).

namespace App\Services;

class CronAuth
{
    const MIN_PANJANG = 16;

    public static function valid(string $diberi, ?string $kunciServer = null): bool
    {
        $kunciServer ??= (string) (getenv('CRON_KEY') ?: ($_ENV['CRON_KEY'] ?? ''));

        if (strlen($kunciServer) < self::MIN_PANJANG) return false;

        return hash_equals($kunciServer, $diberi);
    }
}
