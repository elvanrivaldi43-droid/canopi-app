<?php
// FILE: app/Models/Kasbon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kasbon extends Model
{
    protected $table = 'kasbon';

    protected $fillable = [
        'user_id', 'tanggal', 'nominal', 'keterangan',
        'kategori', 'kategori_lainnya',
        'cicilan_per_bulan', 'jumlah_cicilan', 'cicilan_ke',
        'sisa_kasbon', 'status', 'ditunda_sampai',
        'alasan_tolak', 'ttd_digital', 'ttd_tanggal',
        'approved_oleh', 'ditolak_oleh', 'ditolak_at',
    ];

    protected $casts = [
        'tanggal'        => 'date',
        'ditunda_sampai' => 'date',
        'ttd_tanggal'    => 'datetime',
        'ditolak_at'     => 'datetime',
    ];

    const KATEGORI = [
        'kebutuhan_pribadi' => '🏠 Kebutuhan Pribadi',
        'kesehatan'         => '🏥 Kesehatan',
        'pendidikan'        => '📚 Pendidikan',
        'renovasi_rumah'    => '🔨 Renovasi Rumah',
        'lainnya'           => '📝 Lainnya',
    ];

    const STATUS = [
        'pending'  => '⏳ Menunggu',
        'aktif'    => '🔴 Aktif',
        'lunas'    => '✅ Lunas',
        'ditolak'  => '❌ Ditolak',
        'ditunda'  => '⏸ Ditunda',
        'macet'    => '⚠️ Macet',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedOleh()
    {
        return $this->belongsTo(User::class, 'approved_oleh');
    }

    public function ditolakOleh()
    {
        return $this->belongsTo(User::class, 'ditolak_oleh');
    }

    public function kategoriLabel(): string
    {
        if ($this->kategori === 'lainnya' && $this->kategori_lainnya) {
            return '📝 ' . $this->kategori_lainnya;
        }
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    // Total cicilan aktif user (untuk cek batas aman)
    public static function totalCicilanAktif(int $userId): float
    {
        return self::where('user_id', $userId)
                   ->whereIn('status', ['aktif'])
                   ->where(function($q) {
                       $q->whereNull('ditunda_sampai')
                         ->orWhere('ditunda_sampai', '<', today());
                   })
                   ->sum('cicilan_per_bulan');
    }

    // Jumlah kasbon aktif user
    public static function jumlahAktif(int $userId): int
    {
        return self::where('user_id', $userId)
                   ->whereIn('status', ['aktif','pending','ditunda'])
                   ->count();
    }
}