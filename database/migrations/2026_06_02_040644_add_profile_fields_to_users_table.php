<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rekening (kolom baru)
            $table->string('nama_bank')->nullable()->after('foto');
            $table->string('no_rekening', 30)->nullable()->after('nama_bank');
            $table->string('atas_nama')->nullable()->after('no_rekening');
            // Tipe gaji (kolom baru)
            $table->enum('tipe_gaji', ['harian', 'bulanan', 'project'])->default('harian')->after('atas_nama');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nama_bank', 'no_rekening', 'atas_nama', 'tipe_gaji'
            ]);
        });
    }
};