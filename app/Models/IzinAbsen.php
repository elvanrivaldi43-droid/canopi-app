<?php
// FILE: app/Models/IzinAbsen.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IzinAbsen extends Model
{
    protected $table = 'izin_absen';

    protected $fillable = [
        'user_id', 'tanggal', 'tipe', 'alasan',
        'foto_surat', 'status', 'catatan_mandor',
        'diproses_oleh', 'diproses_at',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'diproses_at' => 'datetime',
    ];

    // Jenis izin
    const TIPE = [
        'sakit'      => '🏥 Sakit',
        'izin'       => '📋 Izin',
        'cuti'       => '🌴 Cuti',
        'dinas_luar' => '🚗 Dinas Luar',
    ];

    // Warna status
    const WARNA_STATUS = [
        'pending'  => '#F59E0B',
        'approved' => '#10B981',
        'rejected' => '#EF4444',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diprosesOleh()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function tipeLabel(): string
    {
        return self::TIPE[$this->tipe] ?? $this->tipe;
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'  => '⏳ Menunggu',
            'approved' => '✅ Disetujui',
            'rejected' => '❌ Ditolak',
            default    => '-',
        };
    }

    public function warnaStatus(): string
    {
        return self::WARNA_STATUS[$this->status] ?? '#64748B';
    }

    // Sakit langsung approved
    public static function buatSakit(int $userId, string $tanggal, string $alasan, ?string $fotoSurat = null): self
    {
        return self::create([
            'user_id'    => $userId,
            'tanggal'    => $tanggal,
            'tipe'       => 'sakit',
            'alasan'     => $alasan,
            'foto_surat' => $fotoSurat,
            'status'     => 'approved', // langsung approved
        ]);
    }
}