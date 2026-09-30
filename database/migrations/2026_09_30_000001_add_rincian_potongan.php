<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Production dipasang lewat docs/sql/2026-09-30-rincian-potongan.sql (auto-deploy
// tidak menjalankan migration) — penjaga hasColumn mencegah "column already exists".
return new class extends Migration
{
    public function up(): void
    {
        foreach (['absensi', 'slip_gaji'] as $t) {
            if (!Schema::hasTable($t) || Schema::hasColumn($t, 'rincian_potongan')) continue;
            Schema::table($t, fn (Blueprint $table) => $table->text('rincian_potongan')->nullable()->after('potongan_telat'));
        }
    }

    public function down(): void
    {
        foreach (['absensi', 'slip_gaji'] as $t) {
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'rincian_potongan')) continue;
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('rincian_potongan'));
        }
    }
};
