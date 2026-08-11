<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalLibur extends Model
{
    protected $table = 'jadwal_libur';

    protected $fillable = [
        'user_id', 'tanggal', 'jenis', 'alasan',
        'status', 'diproses_oleh', 'diproses_at',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'diproses_at' => 'datetime',
    ];

    const JENIS = [
        'tambah' => '➕ Tambah Libur',
        'batal'  => '🚫 Batalkan Libur Default',
    ];

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

    public function jenisLabel(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
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
}
