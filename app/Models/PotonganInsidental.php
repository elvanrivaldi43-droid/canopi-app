<?php
// FILE: app/Models/PotonganInsidental.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotonganInsidental extends Model
{
    protected $table = 'potongan_insidental';

    protected $fillable = [
        'user_id', 'keterangan', 'nominal_total',
        'jumlah_cicilan', 'cicilan_per_bulan', 'cicilan_ke',
        'sisa', 'status', 'input_oleh', 'tanggal_mulai',
    ];

    protected $casts = ['tanggal_mulai' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function inputOleh() { return $this->belongsTo(User::class, 'input_oleh'); }
}