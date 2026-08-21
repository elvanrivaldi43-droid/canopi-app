<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL sudah dijalankan manual di production sebelum push (lihat CLAUDE.md).
        // Guard ini biar `artisan migrate` tidak crash "Duplicate column".
        if (Schema::hasColumn('rab_skill', 'default_role')) return;

        Schema::table('rab_skill', function (Blueprint $table) {
            $table->enum('default_role', ['tukang', 'kenek', 'tukang_kenek', 'manual'])
                  ->default('manual')
                  ->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('rab_skill', function (Blueprint $table) {
            $table->dropColumn('default_role');
        });
    }
};
