<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name'           => 'Elvan Rivaidi',
                'email'          => 'elvan@kanopibsd.co.id',
                'password'       => Hash::make('inspirasikopi'),
                'level'          => 1,
                'jabatan'        => 'Owner',
                'no_hp'          => null,
                'foto'           => null,
                'gaji_harian'    => 0,
                'uang_makan'     => 0,
                'gaji_bulanan'   => 0,
                'jam_masuk'      => '07:00:00',
                'jam_pulang'     => '17:00:00',
                'status'         => 'aktif',
                'tgl_masuk_kerja'=> now(),
                'alamat'         => null,
                'email_verified_at' => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }
}