<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom `user_id` + unique (tanggal, user_id) di kode_absen dulu dipasang lewat SQL manual
// di production (5 Agustus, kode absen per-karyawan) dan tidak pernah masuk migrasi.
// Akibatnya instalasi BARU (fresh migrate) tidak punya keduanya, padahal:
//   - KodeAbsen::kodeHariIniUntuk() mencari kode per user_id, dan
//   - keatomikan createOrFirst() BERGANTUNG pada unique index itu.
// Migrasi ini idempotent: dilewati kalau kolom/index-nya sudah ada.
return new class extends Migration
{
    const NAMA_INDEX = 'kode_absen_tanggal_user_unique';

    public function up(): void
    {
        // Tabelnya sendiri harus ada dulu: hasColumn()/getIndexes() ikut meledak kalau
        // dijalankan terhadap tabel yang belum dibuat, dan `migrate` berhenti di sini.
        if (!Schema::hasTable('kode_absen')) return;

        if (!Schema::hasColumn('kode_absen', 'user_id')) {
            Schema::table('kode_absen', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('tanggal');
            });
        }

        if (!$this->indexAda()) {
            Schema::table('kode_absen', function (Blueprint $table) {
                $table->unique(['tanggal', 'user_id'], self::NAMA_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('kode_absen')) return;

        if ($this->indexAda()) {
            Schema::table('kode_absen', function (Blueprint $table) {
                $table->dropUnique(self::NAMA_INDEX);
            });
        }
    }

    private function indexAda(): bool
    {
        foreach (Schema::getIndexes('kode_absen') as $index) {
            if (($index['name'] ?? null) === self::NAMA_INDEX) return true;
        }
        return false;
    }
};
