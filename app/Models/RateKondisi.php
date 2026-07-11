<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateKondisi extends Model
{
    protected $table = 'rate_kondisi';

    protected $fillable = [
        'kode', 'nama', 'deskripsi', 'multiplier', 'aktif'
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'aktif'      => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('aktif', 1);
    }

    // Rate dasar tukang & kenek
    const RATE_TUKANG = 170000;
    const RATE_KENEK  = 120000;

    public function getRateTukangFinalAttribute()
    {
        return (int) round(self::RATE_TUKANG * $this->multiplier);
    }

    public function getRateKenekFinalAttribute()
    {
        return (int) round(self::RATE_KENEK * $this->multiplier);
    }
}
