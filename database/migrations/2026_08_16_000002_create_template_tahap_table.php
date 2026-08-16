<?php
// database/migrations/2026_08_16_000002_create_template_tahap_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_tahap', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jenis_project');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_tahap');
    }
};
