<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->string('foto_masuk')->nullable();
            $table->string('foto_pulang')->nullable();
            $table->decimal('lat_masuk', 10, 7)->nullable();
            $table->decimal('lng_masuk', 10, 7)->nullable();
            $table->decimal('lat_pulang', 10, 7)->nullable();
            $table->decimal('lng_pulang', 10, 7)->nullable();
            $table->enum('status', ['hadir','telat','setengah_hari','sakit','izin','diliburkan','alpha'])->default('alpha');
            $table->text('keterangan')->nullable();
            $table->string('foto_surat')->nullable();
            $table->decimal('potongan_telat', 12, 2)->default(0);
            $table->decimal('uang_makan_hari_ini', 12, 2)->default(0);
            $table->decimal('gaji_hari_ini', 12, 2)->default(0);
            $table->boolean('dikoreksi')->default(false);
            $table->string('alasan_koreksi')->nullable();
            $table->foreignId('dikoreksi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
