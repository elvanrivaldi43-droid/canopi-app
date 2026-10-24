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

    // Keputusan Elvan 24 Okt 2026: sakit boleh hari ini, jenis lain minimal H+1.
    public static function tanggalMinimal(string $tipe, string $hariIni): string
    {
        return $tipe === 'sakit' ? $hariIni : date('Y-m-d', strtotime($hariIni . ' +1 day'));
    }

    // null = tanggal boleh. Sakit hari ini setelah absen masuk ditolak: kalau lolos,
    // updateAbsensiDariIzin menimpa hari yang sudah dikerjakan jadi sakit tak dibayar.
    public static function alasanTolakTanggal(string $tipe, string $tanggal, string $hariIni, bool $sudahMasukHariIni): ?string
    {
        if ($tanggal < self::tanggalMinimal($tipe, $hariIni)) {
            return $tipe === 'sakit' ? 'Tanggal sakit tidak boleh sebelum hari ini.' : 'Tanggal izin harus H+1 atau lebih';
        }
        if ($tipe === 'sakit' && $tanggal === $hariIni && $sudahMasukHariIni) {
            return 'Kamu sudah absen masuk hari ini. Minta Owner koreksi absensimu jadi Sakit.';
        }
        return null;
    }

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