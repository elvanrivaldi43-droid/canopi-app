<?php
// FILE: app/Models/KodeAbsen.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeAbsen extends Model
{
    protected $table = 'kode_absen';
    protected $fillable = ['kode', 'tanggal', 'user_id'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Ambil kode hari ini punya satu karyawan, buat baru kalau belum ada
    public static function kodeHariIniUntuk(User $user): string
    {
        $record = self::whereDate('tanggal', today())->where('user_id', $user->id)->first();
        if (!$record) {
            $kode   = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $record = self::create(['kode' => $kode, 'tanggal' => today(), 'user_id' => $user->id]);
        }
        return $record->kode;
    }

    // Validasi kode milik satu karyawan tertentu
    public static function validasiUntuk(User $user, string $inputKode): bool
    {
        return self::whereDate('tanggal', today())
                   ->where('user_id', $user->id)
                   ->where('kode', strtoupper(trim($inputKode)))
                   ->exists();
    }
}