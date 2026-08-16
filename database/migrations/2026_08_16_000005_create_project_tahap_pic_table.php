<?php
// database/migrations/2026_08_16_000005_create_project_tahap_pic_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL sudah dijalankan manual di production sebelum push (lihat CLAUDE.md).
        // Guard ini biar `artisan migrate` tidak crash "table already exists".
        if (Schema::hasTable('project_tahap_pic')) return;

        Schema::create('project_tahap_pic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_tahap_id')->constrained('project_tahap')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('peran', ['tukang', 'kenek']);
            $table->foreignId('ditambahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tahap_pic');
    }
};
