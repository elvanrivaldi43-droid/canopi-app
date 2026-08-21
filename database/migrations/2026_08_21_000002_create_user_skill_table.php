<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_skill')) return;

        Schema::create('user_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Tanpa FK constraint ke rab_skill: tabel itu dibuat manual (bukan lewat
            // migration Laravel, tidak ada migration file-nya), tipe kolom id-nya tidak
            // bisa dipastikan dari sini. Pola sama tahap_master.rab_jenis_kerja_id.
            $table->unsignedBigInteger('rab_skill_id');
            $table->enum('sumber', ['default_role', 'manual']);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skill');
    }
};
