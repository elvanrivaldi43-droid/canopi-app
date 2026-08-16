<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahapMaster extends Model
{
    protected $table = 'tahap_master';

    protected $fillable = [
        'nama', 'rab_jenis_kerja_id', 'tipe', 'urutan', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
