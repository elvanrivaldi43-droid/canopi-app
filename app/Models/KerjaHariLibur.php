<?php
// FILE: app/Models/KerjaHariLibur.php
// Otorisasi "masuk kerja di hari libur" — 1 baris = 1 karyawan = 1 tanggal.
//
// Baris ini MEMBATALKAN libur di tanggal tersebut: hari itu berubah menjadi hari
// kerja biasa (masuk penyebut hari kerja & pembilang kehadiran — lihat
// LiburService::expandAktivasi). Jatah libur karyawan untuk hari itu memang
// terpakai, TANPA hari pengganti; kompensasinya berupa upah 1x gaji harian
// (kolom snapshot di bawah), bukan libur ganti.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KerjaHariLibur extends Model
{
    protected $table = 'kerja_hari_libur';

    protected $fillable = [
        'user_id', 'tanggal', 'diaktifkan_oleh',
        'gaji_harian_snapshot', 'uang_makan_snapshot',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Kunci identitas baris — dipakai firstOrCreate biar klik ulang tidak
    // bikin baris/notifikasi kedua. Tanggal selalu dinormalkan ke Y-m-d.
    public static function kunciUnik(int $userId, $tanggal): array
    {
        return [
            'user_id' => $userId,
            'tanggal' => $tanggal instanceof \DateTimeInterface
                ? $tanggal->format('Y-m-d')
                : (string) $tanggal,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pengaktif()
    {
        return $this->belongsTo(User::class, 'diaktifkan_oleh');
    }
}
