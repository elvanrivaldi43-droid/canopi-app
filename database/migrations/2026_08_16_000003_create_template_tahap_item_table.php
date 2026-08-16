<?php
// database/migrations/2026_08_16_000003_create_template_tahap_item_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL sudah dijalankan manual di production sebelum push (lihat CLAUDE.md).
        // Guard ini biar `artisan migrate` tidak crash "table already exists".
        if (Schema::hasTable('template_tahap_item')) return;

        Schema::create('template_tahap_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_tahap_id')->constrained('template_tahap')->cascadeOnDelete();
            $table->foreignId('tahap_master_id')->constrained('tahap_master')->cascadeOnDelete();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_tahap_item');
    }
};
