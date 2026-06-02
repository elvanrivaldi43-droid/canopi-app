<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TunjanganSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tunjangan_master')->insert([
            ['nama_tunjangan' => 'Tunjangan Transport', 'tipe' => 'bulanan', 'nominal_default' => 300000, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama_tunjangan' => 'Tunjangan Jabatan', 'tipe' => 'bulanan', 'nominal_default' => 500000, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama_tunjangan' => 'Tunjangan Skill', 'tipe' => 'bulanan', 'nominal_default' => 200000, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama_tunjangan' => 'Tunjangan Makan Luar Kota', 'tipe' => 'harian', 'nominal_default' => 75000, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama_tunjangan' => 'Tunjangan Service Motor', 'tipe' => 'bulanan', 'nominal_default' => 300000, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}