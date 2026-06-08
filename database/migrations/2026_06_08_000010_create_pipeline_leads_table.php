<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nama_customer');
            $table->string('no_hp', 20);
            $table->text('alamat')->nullable();
            $table->enum('produk', ['kanopi', 'pagar', 'tralis', 'tenda']);
            $table->enum('sumber_lead', ['Instagram', 'WhatsApp', 'Referensi', 'Google', 'Spanduk', 'Lainnya']);
            $table->enum('status', ['lead', 'dihubungi', 'dijadwalkan', 'dikunjungi', 'ditawar', 'deal', 'tidak_jadi'])->default('lead');
            $table->decimal('estimasi_nilai', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->date('tanggal_jadwal')->nullable();
            $table->time('jam_jadwal')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_leads');
    }
};
