<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal','tukar') NOT NULL");
        Schema::table('jadwal_libur', function (Blueprint $table) {
            $table->date('tanggal_baru')->nullable()->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_libur', function (Blueprint $table) {
            $table->dropColumn('tanggal_baru');
        });
        DB::statement("ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal') NOT NULL");
    }
};
