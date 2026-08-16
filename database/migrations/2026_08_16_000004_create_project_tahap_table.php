<?php
// database/migrations/2026_08_16_000004_create_project_tahap_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL sudah dijalankan manual di production sebelum push (lihat CLAUDE.md).
        // Guard ini biar `artisan migrate` tidak crash "table already exists".
        if (Schema::hasTable('project_tahap')) return;

        Schema::create('project_tahap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('tahap_master_id')->nullable()->constrained('tahap_master')->nullOnDelete();
            $table->string('nama_tahap');
            $table->integer('urutan')->default(0);
            $table->enum('status', ['belum', 'sedang', 'selesai'])->default('belum');
            $table->decimal('qty', 12, 2)->nullable();
            $table->string('satuan')->nullable();
            $table->date('tanggal_mulai_target')->nullable();
            $table->date('tanggal_selesai_target')->nullable();
            $table->date('tanggal_mulai_aktual')->nullable();
            $table->date('tanggal_selesai_aktual')->nullable();
            $table->integer('jumlah_tukang_disarankan')->nullable();
            $table->integer('jumlah_kenek_disarankan')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tahap');
    }
};
