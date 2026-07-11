<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UjianSesi extends Model
{
    protected $table = 'ujian_sesi';

    protected $fillable = [
        'user_id', 'periode', 'tahun',
        'mulai_pada', 'selesai_pada', 'batas_waktu',
        'status', 'nilai', 'jumlah_benar', 'jumlah_soal',
    ];

    protected $casts = [
        'mulai_pada'   => 'datetime',
        'selesai_pada' => 'datetime',
        'batas_waktu'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jawaban()
    {
        return $this->hasMany(UjianJawaban::class, 'sesi_id');
    }

    // Cek apakah sesi sudah expired (lewat 30 menit)
    public function isExpired(): bool
    {
        if ($this->status === 'berlangsung' && $this->batas_waktu) {
            return now()->isAfter($this->batas_waktu);
        }
        return false;
    }

    // Sisa waktu dalam detik
    public function sisaWaktuDetik(): int
    {
        if ($this->batas_waktu && $this->status === 'berlangsung') {
            return max(0, now()->diffInSeconds($this->batas_waktu, false));
        }
        return 0;
    }
}