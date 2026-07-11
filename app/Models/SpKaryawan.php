<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpKaryawan extends Model
{
    protected $table = 'sp_karyawan';

    protected $fillable = [
        'user_id', 'level_sp', 'alasan', 'trigger_otomatis', 'status',
        'tanggal_sp', 'tanggal_aktif', 'tanggal_pulih',
        'dikonfirmasi_oleh', 'catatan_owner',
        'bulan_bersih_berturut', 'reset_timer_pada',
    ];

    protected $casts = [
        'tanggal_sp'     => 'date',
        'tanggal_aktif'  => 'date',
        'tanggal_pulih'  => 'date',
        'reset_timer_pada' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dikonfirmasiOleh()
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh');
    }

    // SP aktif seorang user
    public static function spAktif($userId)
    {
        return static::where('user_id', $userId)
            ->where('status', 'aktif')
            ->orderByDesc('tanggal_aktif')
            ->first();
    }

    // Label SP
    public function labelSp(): string
    {
        return strtoupper($this->level_sp);
    }

    // Warna badge SP
    public function warnaSp(): string
    {
        return match($this->level_sp) {
            'sp1' => '#f59e0b',
            'sp2' => '#f97316',
            'sp3' => '#ef4444',
            default => '#6b7280',
        };
    }
}