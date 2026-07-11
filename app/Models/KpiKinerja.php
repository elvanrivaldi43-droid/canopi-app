<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiKinerja extends Model
{
    protected $table = 'poin_kinerja';

    protected $fillable = [
        'user_id', 'bulan', 'tahun',
        'poin_kehadiran', 'poin_tugas', 'poin_leads', 'poin_bbm', 'poin_komplain',
        'total_poin', 'bintang', 'is_alpha', 'bonus_nominal',
        'is_bintang_jabatan', 'detail_kehadiran', 'detail_tugas', 'detail_bbm',
        'dihitung_pada',
    ];

    protected $casts = [
        'detail_kehadiran' => 'array',
        'detail_tugas'     => 'array',
        'detail_bbm'       => 'array',
        'dihitung_pada'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Hitung bintang dari total poin
    public static function hitungBintang(float $poin): int
    {
        if ($poin >= 90) return 5;
        if ($poin >= 75) return 4;
        if ($poin >= 60) return 3;
        if ($poin >= 45) return 2;
        return 1;
    }

    // Label bintang
    public static function labelBintang(int $bintang): string
    {
        return str_repeat('⭐', $bintang);
    }

    // Hitung bonus dari bintang
    public static function hitungBonus(int $bintang, bool $isAlpha): int
    {
        if ($isAlpha) return 0;
        return match($bintang) {
            5 => 300000,
            4 => 150000,
            3 => 75000,
            default => 0,
        };
    }
}