<?php
// FILE: app/Models/TabunganKaryawan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabunganKaryawan extends Model
{
    protected $table = 'tabungan_karyawan';

    protected $fillable = [
        'user_id',
        'tabungan_wajib_total',
        'tabungan_lebaran_total',
        'tabungan_lebaran_per_bulan',
        'tabungan_lebaran_cair',
    ];

    protected $casts = [
        'tabungan_lebaran_cair' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
}