<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LuarKota extends Model
{
    protected $table = 'luar_kota';

    protected $fillable = [
        'user_id', 'dibuat_oleh', 'tanggal_mulai', 'tanggal_selesai',
        'lokasi', 'keterangan', 'status',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function karyawan()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // Scope: hanya yang aktif
    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    // Scope: aktif pada tanggal tertentu
    public function scopeAktifPadaTanggal($query, $tanggal = null)
    {
        $tanggal = $tanggal ?? today();
        return $query->where('status', 'aktif')
                     ->where('tanggal_mulai', '<=', $tanggal)
                     ->where('tanggal_selesai', '>=', $tanggal);
    }

    // Cek apakah masih aktif hari ini
    public function isAktifHariIni(): bool
    {
        return $this->status === 'aktif'
            && today()->between($this->tanggal_mulai, $this->tanggal_selesai);
    }

    // Durasi dalam hari
    public function durasiHari(): int
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    // Label status
    public function labelStatus(): string
    {
        return match($this->status) {
            'aktif'      => 'Aktif',
            'selesai'    => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
            default      => '-',
        };
    }

    // Warna status
    public function warnaStatus(): string
    {
        return match($this->status) {
            'aktif'      => '#f59e0b',
            'selesai'    => '#22c55e',
            'dibatalkan' => '#ef4444',
            default      => '#64748b',
        };
    }

    // Static: cek apakah user sedang luar kota hari ini
    public static function sedangLuarKota(int $userId): bool
    {
        return static::where('user_id', $userId)
            ->aktifPadaTanggal()
            ->exists();
    }

    // Static: ambil data luar kota aktif user hari ini
    public static function getAktif(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->aktifPadaTanggal()
            ->first();
    }
}