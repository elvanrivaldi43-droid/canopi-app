<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_leads', function (Blueprint $table) {
            $table->text('lokasi_video')->nullable()->after('lokasi_foto');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_leads', function (Blueprint $table) {
            $table->dropColumn('lokasi_video');
        });
    }
};
