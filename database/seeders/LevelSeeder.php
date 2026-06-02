<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('levels')->insert([
            [
                'id' => 1,
                'nama_level' => 'Owner',
                'deskripsi' => 'Pemilik bisnis, akses penuh semua modul',
                'redirect_to' => '/owner/dashboard',
            ],
            [
                'id' => 2,
                'nama_level' => 'Admin Operasional',
                'deskripsi' => 'Admin Sales & Project, saling backup',
                'redirect_to' => '/admin/dashboard',
            ],
            [
                'id' => 3,
                'nama_level' => 'Supervisor Lapangan',
                'deskripsi' => 'Surveyor & Mandor, saling backup',
                'redirect_to' => '/supervisor/dashboard',
            ],
            [
                'id' => 4,
                'nama_level' => 'Marketing',
                'deskripsi' => 'Sosmed, konten, iklan, dan leads digital',
                'redirect_to' => '/marketing/dashboard',
            ],
            [
                'id' => 5,
                'nama_level' => 'Teknisi',
                'deskripsi' => 'Tukang las & helper, produksi & pemasangan',
                'redirect_to' => '/teknisi/dashboard',
            ],
            [
                'id' => 6,
                'nama_level' => 'Driver',
                'deskripsi' => 'Logistik & transportasi',
                'redirect_to' => '/driver/dashboard',
            ],
            [
                'id' => 7,
                'nama_level' => 'Admin Toko Besi',
                'deskripsi' => 'Akses minimal, sync Olsera saja',
                'redirect_to' => '/toko/dashboard',
            ],
        ]);
    }
}