<?php
// database/migrations/2026_08_16_000001_create_tahap_master_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahap_master', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // TANPA foreign key constraint ke rab_jenis_kerja: tabel itu dibuat manual
            // (bukan lewat migration Laravel, tidak ada migration file-nya), tipe kolom
            // id-nya tidak bisa dipastikan dari sini. Konsisten dengan skill_default di
            // tabel yang sama yang juga tanpa FK — link ini opsional & dibaca saja.
            $table->unsignedBigInteger('rab_jenis_kerja_id')->nullable();
            $table->enum('tipe', ['fab', 'inst'])->nullable();
            $table->integer('urutan')->default(99);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahap_master');
    }
};
