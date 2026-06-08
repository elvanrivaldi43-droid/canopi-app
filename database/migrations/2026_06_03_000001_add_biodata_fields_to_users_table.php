<?php
// FILE: database/migrations/2026_06_03_000001_add_biodata_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Biodata
            $table->string('tempat_lahir')->nullable()->after('atas_nama');
            $table->date('tgl_lahir')->nullable()->after('tempat_lahir');
            $table->string('no_ktp', 20)->nullable()->after('tgl_lahir');
            $table->string('no_kk', 20)->nullable()->after('no_ktp');
            // Kontak darurat
            $table->string('darurat_nama')->nullable()->after('no_kk');
            $table->string('darurat_no_hp', 20)->nullable()->after('darurat_nama');
            $table->string('darurat_hubungan')->nullable()->after('darurat_no_hp');
            // Info tambahan
            $table->string('ukuran_baju')->nullable()->after('darurat_hubungan');
            $table->enum('status_nikah', ['belum_menikah','menikah','cerai'])->nullable()->after('ukuran_baju');
            $table->tinyInteger('jumlah_tanggungan')->default(0)->after('status_nikah');
            $table->string('golongan_darah', 5)->nullable()->after('jumlah_tanggungan');
            $table->string('no_bpjs_kesehatan', 20)->nullable()->after('golongan_darah');
            $table->string('no_bpjs_ketenagakerjaan', 20)->nullable()->after('no_bpjs_kesehatan');
            // Status registrasi
            $table->enum('status_registrasi', ['menunggu','lengkap'])->default('lengkap')->after('no_bpjs_ketenagakerjaan');
            // Bonus
            $table->bigInteger('uang_bonus')->default(0)->after('uang_makan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tempat_lahir','tgl_lahir','no_ktp','no_kk',
                'darurat_nama','darurat_no_hp','darurat_hubungan',
                'ukuran_baju','status_nikah','jumlah_tanggungan',
                'golongan_darah','no_bpjs_kesehatan','no_bpjs_ketenagakerjaan',
                'status_registrasi','uang_bonus',
            ]);
        });
    }
};
