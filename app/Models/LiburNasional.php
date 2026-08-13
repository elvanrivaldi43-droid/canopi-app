<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiburNasional extends Model
{
    protected $table = 'libur_nasional';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai', 'dibuat_oleh'];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function piket()
    {
        return $this->hasMany(LiburNasionalPiket::class);
    }

    public function labelRentang(): string
    {
        if ($this->tanggal_mulai->isSameDay($this->tanggal_selesai)) {
            return $this->tanggal_mulai->translatedFormat('d F Y');
        }
        return $this->tanggal_mulai->translatedFormat('d F').' - '.$this->tanggal_selesai->translatedFormat('d F Y');
    }
}
