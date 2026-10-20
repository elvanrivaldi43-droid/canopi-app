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
            'menunggu'   => 'Menunggu',
            'ditolak'    => 'Ditolak',
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
            'menunggu'   => '#3b82f6',
            'ditolak'    => '#ef4444',
            default      => '#64748b',
        };
    }

    // ─── Ajuan kerja di luar kantor (spec 2026-10-20) ──────────────────
    // Hanya level yang wajib GPS siang yang perlu (dan boleh) minta pengecualian.
    public static function bolehAjukan(int $level, bool $sudahMasuk, bool $sudahPulang, bool $adaAktifAtauMenunggu): ?string
    {
        if (!\App\Http\Controllers\AbsensiController::gpsSiangWajib($level)) {
            return 'Fitur ini khusus Admin Operasional & Admin Toko Besi.';
        }
        if (!$sudahMasuk)          return 'Absen masuk dulu di kantor, baru ajukan kerja di luar kantor.';
        if ($sudahPulang)          return 'Kamu sudah absen pulang hari ini.';
        if ($adaAktifAtauMenunggu) return 'Hari ini sudah ada ajuan atau mode luar kota.';
        return null;
    }

    public static function alasanTolakProses(string $status, string $tanggal, int $pemilikId, int $aktorId, string $hariIni): ?string
    {
        if ($status !== 'menunggu')  return 'Ajuan ini sudah diproses.';
        if ($tanggal !== $hariIni)   return 'Ajuan sudah kedaluwarsa (bukan untuk hari ini).';
        if ($pemilikId === $aktorId) return 'Tidak bisa memproses ajuan sendiri.';
        return null;
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