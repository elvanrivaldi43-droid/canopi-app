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
        Schema::create('izin_absen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('tanggal');
            $table->enum('tipe', ['izin','sakit','setengah_hari','keluarga_meninggal']);
            $table->text('alasan');
            $table->string('foto_surat')->nullable();
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan_mandor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izin_absen');
    }
};
