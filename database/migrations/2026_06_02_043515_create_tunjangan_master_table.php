<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tunjangan_master', function (Blueprint $table) {
            $table->id();
            $table->string('nama_tunjangan');
            $table->enum('tipe', ['harian', 'bulanan', 'project']);
            $table->decimal('nominal_default', 12, 2)->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tunjangan_master');
    }
};