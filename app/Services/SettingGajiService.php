<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Saklar kebijakan gaji yang dulu DIPAKU DI KODE (keputusan Elvan 31 Ags 2026):
 *  - Bonus KPI: ditunda ("belum bisa nyalakan sekarang ... mungkin Oktober atau Desember").
 *  - Tabungan wajib Rp 100.000: karyawan belum diberi tahu, belum boleh memotong.
 *    Barisnya TETAP tampil di slip (Rp 0) — transparan, bukan disembunyikan.
 *
 * Nominalnya tidak ikut disetel (tetap konstanta di GajiService) — yang bisa diubah
 * Owner hanya NYALA/MATI, sesuai permintaan.
 *
 * Method statis di bawah sengaja MURNI (tanpa DB) supaya bisa dites tanpa database;
 * pembacaan tabelnya dipisah di ambil() dan tahan-banting kalau tabel belum ada.
 */
class SettingGajiService
{
    /** Baca baris setting. Tabel belum ada / DB error -> null (semua saklar MATI). */
    public static function ambil(): ?object
    {
        try {
            return DB::table('setting_gaji')->where('id', 1)->first();
        } catch (\Throwable $e) {
            return null;   // tabel belum dibuat di production -> jangan mematikan aplikasi
        }
    }

    /**
     * Baca satu saklar. DEFAULT WAJIB MATI: kalau baris/kolomnya belum ada, jangan
     * sampai fallback-nya menyalakan potongan yang belum disetujui karyawan.
     */
    public static function aktifDari(?object $setting, string $kolom): bool
    {
        if (!$setting || !isset($setting->$kolom)) return false;
        return (bool) (int) $setting->$kolom;
    }

    /** Nominal bonus KPI yang benar-benar dibayar. */
    public static function nilaiBonusKpi(float|int $nominal, bool $aktif): int
    {
        return $aktif ? (int) $nominal : 0;
    }

    /**
     * Boleh tidaknya slip dihitung ulang (hapus + generate lagi).
     * Slip "dibayar" TIDAK PERNAH boleh: prosesBayar sudah memajukan cicilan kasbon dan
     * menambah saldo tabungan — mengulangnya akan menggandakan efek itu, dan bukti
     * transfer yang sudah dikirim ke karyawan jadi tak cocok dengan slipnya.
     * Status tak dikenal juga ditolak (aman by default).
     */
    public static function bolehHitungUlang(?string $status): bool
    {
        return in_array($status, ['draft', 'menunggu_konfirmasi'], true);
    }

    /** Nominal tabungan wajib yang benar-benar dipotong. */
    public static function nilaiTabunganWajib(float|int $nominal, bool $aktif): int
    {
        return $aktif ? (int) $nominal : 0;
    }
}
