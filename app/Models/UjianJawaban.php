<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UjianJawaban extends Model
{
    protected $table = 'ujian_jawaban';

    protected $fillable = [
        'sesi_id', 'soal_id', 'urutan',
        'jawaban_karyawan', 'is_benar',
    ];

    public function soal()
    {
        return $this->belongsTo(UjianSoal::class, 'soal_id');
    }

    public function sesi()
    {
        return $this->belongsTo(UjianSesi::class, 'sesi_id');
    }
}