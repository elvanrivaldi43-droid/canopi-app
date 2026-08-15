<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Penjaga hasTable dipasang seragam di seluruh migrasi fitur ini: kalau tabel
    // sasarannya belum ada, migrasi dilewati diam-diam supaya `migrate` tidak berhenti
    // di migrasi fitur (kolom production tetap dipasang lewat docs/sql/...).
    //
    // Penjaga hasColumn PER KOLOM juga wajib: di production kolom-kolom ini dipasang
    // MANUAL lewat docs/sql/2026-08-15-kerja-hari-libur.sql lebih dulu (auto-deploy
    // cuma FTP sync, tidak pernah menjalankan migration). Jadi begitu `migrate`
    // dijalankan di mana pun, kolomnya sudah ada dan migrasi ini akan gagal dengan
    // "column already exists" — menghentikan seluruh antrian migrasi setelahnya.
    public function up(): void
    {
        if (!Schema::hasTable('absensi')) return;

        Schema::table('absensi', function (Blueprint $table) {
            // Penanda: hari ini sebenarnya libur, tapi karyawan diminta/berkenan masuk.
            if (!Schema::hasColumn('absensi', 'kerja_hari_libur')) {
                $table->boolean('kerja_hari_libur')->default(false)->after('dikoreksi_oleh');
            }
            // Upah kerja hari libur (KOTOR, dari snapshot otorisasi) — buat audit
            // dan buat komponen tambahan pendapatan pegawai bulanan.
            if (!Schema::hasColumn('absensi', 'upah_hari_libur')) {
                $table->decimal('upah_hari_libur', 12, 2)->default(0)->after('kerja_hari_libur');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('absensi')) return;

        Schema::table('absensi', function (Blueprint $table) {
            // Dijaga satu per satu: dropColumn atas kolom yang tidak ada akan error,
            // dan rollback yang gagal di tengah meninggalkan skema setengah jadi.
            if (Schema::hasColumn('absensi', 'upah_hari_libur')) {
                $table->dropColumn('upah_hari_libur');
            }
            if (Schema::hasColumn('absensi', 'kerja_hari_libur')) {
                $table->dropColumn('kerja_hari_libur');
            }
        });
    }
};
