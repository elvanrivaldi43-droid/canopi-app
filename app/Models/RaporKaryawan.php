<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporKaryawan extends Model
{
    protected $table = 'rapor_karyawan';

    protected $fillable = [
        'user_id', 'periode', 'tahun',
        'nilai_kpi', 'nilai_ujian', 'nilai_sp',
        'nilai_total', 'kelas_sebelumnya', 'kelas_baru',
        'kenaikan_gaji', 'kelas_naik', 'status', 'id_ujian_sesi',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ujianSesi()
    {
        return $this->belongsTo(UjianSesi::class, 'id_ujian_sesi');
    }

    // Label kelas dengan emoji
    public static function labelKelas(string $kelas): string
    {
        return match($kelas) {
            'platinum' => '🥇 Platinum',
            'gold'     => '🥈 Gold',
            'silver'   => '🥉 Silver',
            'bronze'   => '⚪ Bronze',
            'red_zone' => '🔴 Red Zone',
            default    => '-',
        };
    }

    // Warna per kelas untuk tampilan
    public static function warnaKelas(string $kelas): string
    {
        return match($kelas) {
            'platinum' => '#e5e7eb',
            'gold'     => '#fbbf24',
            'silver'   => '#94a3b8',
            'bronze'   => '#92400e',
            'red_zone' => '#ef4444',
            default    => '#6b7280',
        };
    }

    // Tentukan kelas dari nilai total
    public static function tentukanKelas(float $nilai): string
    {
        if ($nilai >= 90) return 'platinum';
        if ($nilai >= 75) return 'gold';
        if ($nilai >= 60) return 'silver';
        if ($nilai >= 45) return 'bronze';
        return 'red_zone';
    }

    // Nominal kenaikan gaji permanen saat naik kelas
    public static function kenaikanGaji(string $kelas): int
    {
        return match($kelas) {
            'platinum' => 200000,
            'gold'     => 150000,
            'silver'   => 100000,
            default    => 0,
        };
    }
}