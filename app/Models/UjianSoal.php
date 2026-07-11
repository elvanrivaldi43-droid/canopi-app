<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UjianSoal extends Model
{
    protected $table = 'ujian_soal';

    protected $fillable = [
        'jabatan_level', 'pertanyaan',
        'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d',
        'jawaban_benar', 'is_aktif', 'dibuat_oleh',
    ];

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public static function namaJabatan(int $level): string
    {
        return match($level) {
            2 => 'Admin Operasional',
            3 => 'Supervisor',
            4 => 'Marketing',
            5 => 'Teknisi',
            6 => 'Driver',
            default => 'Umum',
        };
    }
}