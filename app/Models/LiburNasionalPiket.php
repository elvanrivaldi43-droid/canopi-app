<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiburNasionalPiket extends Model
{
    protected $table = 'libur_nasional_piket';

    protected $fillable = ['libur_nasional_id', 'user_id', 'tanggal'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function liburNasional()
    {
        return $this->belongsTo(LiburNasional::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
