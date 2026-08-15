<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // `slip_gaji` dibuat lewat SQL manual di production dan TIDAK punya migrasi create
    // di repo ini. Tanpa penjaga hasTable, `php artisan migrate` di instalasi baru
    // berhenti DI SINI (tabel tidak ada) — bukan di penyebab aslinya — dan semua
    // migrasi sesudahnya ikut tidak jalan. Kolom aslinya tetap dipasang lewat
    // docs/sql/2026-08-15-kerja-hari-libur.sql di production.
    public function up(): void
    {
        if (!Schema::hasTable('slip_gaji')) return;

        Schema::table('slip_gaji', function (Blueprint $table) {
            if (!Schema::hasColumn('slip_gaji', 'hari_kerja_libur')) {
                $table->integer('hari_kerja_libur')->default(0)->after('hari_izin');
            }
            if (!Schema::hasColumn('slip_gaji', 'upah_hari_libur')) {
                $table->decimal('upah_hari_libur', 12, 2)->default(0)->after('gaji_pokok');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('slip_gaji')) return;

        // Per-kolom, sama seperti up(). Kolom aslinya dipasang lewat SQL manual di
        // production, jadi keadaan "satu kolom ada, satu tidak" benar-benar mungkin —
        // dan dropColumn dua kolom sekaligus di keadaan itu berhenti dengan error
        // setelah kolom pertama terlanjur jatuh, menyisakan tabel setengah jadi.
        Schema::table('slip_gaji', function (Blueprint $table) {
            if (Schema::hasColumn('slip_gaji', 'hari_kerja_libur')) {
                $table->dropColumn('hari_kerja_libur');
            }
            if (Schema::hasColumn('slip_gaji', 'upah_hari_libur')) {
                $table->dropColumn('upah_hari_libur');
            }
        });
    }
};
