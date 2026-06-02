<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('level')->default(5)->after('id')->comment('1=Owner,2=Admin Ops,3=Supervisor,4=Marketing,5=Teknisi,6=Driver,7=Admin Toko');
            $table->string('jabatan')->nullable()->after('level');
            $table->string('no_hp', 20)->nullable()->after('jabatan');
            $table->string('foto')->nullable()->after('no_hp');
            $table->decimal('gaji_harian', 12, 2)->default(0)->after('foto');
            $table->decimal('uang_makan', 10, 2)->default(0)->after('gaji_harian');
            $table->decimal('gaji_bulanan', 12, 2)->default(0)->after('uang_makan');
            $table->time('jam_masuk')->default('07:30:00')->after('gaji_bulanan');
            $table->time('jam_pulang')->default('17:00:00')->after('jam_masuk');
            $table->enum('status', ['aktif', 'nonaktif', 'sp1', 'sp2', 'sp3'])->default('aktif')->after('jam_pulang');
            $table->date('tgl_masuk_kerja')->nullable()->after('status');
            $table->string('alamat')->nullable()->after('tgl_masuk_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'level', 'jabatan', 'no_hp', 'foto',
                'gaji_harian', 'uang_makan', 'gaji_bulanan',
                'jam_masuk', 'jam_pulang', 'status',
                'tgl_masuk_kerja', 'alamat'
            ]);
        });
    }
};