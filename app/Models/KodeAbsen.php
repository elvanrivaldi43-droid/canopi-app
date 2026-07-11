<?php
// FILE: app/Models/KodeAbsen.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeAbsen extends Model
{
    protected $table = 'kode_absen';
    protected $fillable = ['kode', 'tanggal'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Ambil kode hari ini, buat baru kalau belum ada
    public static function kodeHariIni(): string
    {
        $record = self::whereDate('tanggal', today())->first();
        if (!$record) {
            $kode   = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $record = self::create(['kode' => $kode, 'tanggal' => today()]);
        }
        return $record->kode;
    }

    // Validasi kode
    public static function validasi(string $inputKode): bool
    {
        return self::whereDate('tanggal', today())
                   ->where('kode', strtoupper(trim($inputKode)))
                   ->exists();
    }
}