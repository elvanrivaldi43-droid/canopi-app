<?php

namespace App\Services;

// Guard konflik autosave RAB (Utang #9): deteksi "tab/perangkat lain sudah menyimpan
// sejak klien ini memuat datanya" TANPA kolom versi baru — sidik jari = md5 snapshot
// tersimpan. Murni tanpa dependency framework supaya bisa dites standalone
// (tests/rab/test_snapshot_guard.php).
class SnapshotGuard
{
    // true = TOLAK simpanan (basis klien sudah basi). Aturan kompat:
    // - $baseMd5 null/'' -> false (klien lama tak mengirim basis; perilaku lama tetap jalan)
    // - $stored null/''  -> false (belum pernah ada snapshot; save pertama selalu boleh)
    public static function conflict(?string $stored, ?string $baseMd5): bool
    {
        if ($baseMd5 === null || $baseMd5 === '') return false;
        if ($stored === null || $stored === '') return false;
        return md5($stored) !== $baseMd5;
    }
}
