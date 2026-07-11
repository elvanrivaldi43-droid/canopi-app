<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kendaraan extends Model
{
    protected $table = 'kendaraan';

    protected $fillable = [
        'nama', 'plat', 'jenis', 'standar_km_per_liter', 'is_active',
    ];

    protected $casts = [
        'standar_km_per_liter' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function logBensin()
    {
        return $this->hasMany(LogBensin::class, 'kendaraan_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', 1);
    }
}