<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogBensin extends Model
{
    protected $table = 'log_bensin';

    protected $fillable = [
        'kendaraan_id', 'driver_id', 'tanggal', 'tujuan',
        'km_awal', 'km_akhir', 'liter', 'nominal',
        'km_tempuh', 'konsumsi_aktual', 'status', 'catatan',
        'notif_boros_terkirim',
    ];

    protected $casts = [
        'tanggal'          => 'date',
        'km_awal'          => 'decimal:1',
        'km_akhir'         => 'decimal:1',
        'liter'            => 'decimal:2',
        'km_tempuh'        => 'decimal:1',
        'konsumsi_aktual'  => 'decimal:2',
        'notif_boros_terkirim' => 'boolean',
    ];

    public function kendaraan()
    {
        return $this->belongsTo(Kendaraan::class, 'kendaraan_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // Apakah konsumsi BBM boros (di bawah standar)?
    public function isBoros(): bool
    {
        if (!$this->konsumsi_aktual || !$this->kendaraan) return false;
        return $this->konsumsi_aktual < $this->kendaraan->standar_km_per_liter;
    }

    // Label status
    public function labelStatus(): string
    {
        return $this->status === 'selesai' ? 'Selesai' : 'Dalam Perjalanan';
    }

    // Warna status
    public function warnaStatus(): string
    {
        return $this->status === 'selesai' ? '#22c55e' : '#f59e0b';
    }
}