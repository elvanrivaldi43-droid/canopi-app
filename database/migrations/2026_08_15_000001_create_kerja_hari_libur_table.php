<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kerja_hari_libur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('tanggal');
            $table->foreignId('diaktifkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('gaji_harian_snapshot', 12, 2)->default(0);
            $table->decimal('uang_makan_snapshot', 12, 2)->default(0);
            $table->timestamps();

            // 1 karyawan = 1 otorisasi per tanggal (klik ulang tidak bikin baris kedua)
            $table->unique(['user_id', 'tanggal'], 'kerja_hari_libur_user_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kerja_hari_libur');
    }
};
