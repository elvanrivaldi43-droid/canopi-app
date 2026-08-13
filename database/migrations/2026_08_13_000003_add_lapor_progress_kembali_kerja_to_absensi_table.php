<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->time('jam_lapor_progress')->nullable()->after('jam_absen_siang');
            $table->text('pertanyaan_progress')->nullable()->after('jam_lapor_progress');
            $table->text('jawaban_progress')->nullable()->after('pertanyaan_progress');
            $table->text('kendala_kenapa')->nullable()->after('deskripsi_kendala');
            $table->boolean('potongan_progress_dicatat')->default(false)->after('potongan_siang_dicatat');
            $table->decimal('lat_kembali_kerja', 10, 7)->nullable()->after('kendala_kenapa');
            $table->decimal('lng_kembali_kerja', 10, 7)->nullable()->after('lat_kembali_kerja');
            $table->boolean('gps_valid_kembali_kerja')->nullable()->after('lng_kembali_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropColumn([
                'jam_lapor_progress', 'pertanyaan_progress', 'jawaban_progress',
                'kendala_kenapa', 'potongan_progress_dicatat',
                'lat_kembali_kerja', 'lng_kembali_kerja', 'gps_valid_kembali_kerja',
            ]);
        });
    }
};
