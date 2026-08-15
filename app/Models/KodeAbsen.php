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

    // Huruf/angka yang gampang salah baca (I, O, 0, 1) sengaja tidak dipakai.
    const ALFABET_KODE = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generateKode(): string
    {
        return strtoupper(substr(str_shuffle(self::ALFABET_KODE), 0, 6));
    }

    // Ambil kode hari ini punya satu karyawan, buat baru kalau belum ada.
    // createOrFirst = INSERT dulu; kalau kena unique (tanggal, user_id) baris yang sudah
    // ada yang dipakai. Pola lama (SELECT dulu, baru INSERT) bisa kebobolan kalau dua
    // request datang barengan — mis. Owner klik "Aktifkan Masuk Hari Ini" dua kali —
    // dan menghasilkan DUA kode valid untuk satu karyawan di satu hari.
    public static function kodeHariIniUntuk(User $user): string
    {
        return self::barisHariIniUntuk($user)->kode;
    }

    // Versi yang mengembalikan BARISNYA, bukan cuma kodenya. Dipakai cron pagi:
    // `wasRecentlyCreated` = false berarti kodenya sudah pernah dibuat hari ini,
    // jadi pesan Telegram TIDAK dikirim ulang (insiden 6 Agustus: satu karyawan
    // menerima kode 4x dalam satu pagi karena cron retry + trigger manual).
    public static function barisHariIniUntuk(User $user): self
    {
        return self::createOrFirst(
            ['tanggal' => today()->toDateString(), 'user_id' => $user->id],
            ['kode'    => self::generateKode()]
        );
    }

    /**
     * BACA SAJA — kode hari ini kalau memang sudah ada, null kalau belum. TIDAK MEMBUAT.
     *
     * Dipakai halaman "Kode Absen Hari Ini" (sebuah GET). Dulu halaman itu memanggil
     * kodeHariIniUntuk(), yang MEMBUAT baris kode kalau belum ada. Padahal cron pagi
     * memutuskan kirim/tidak dari `wasRecentlyCreated`: kalau barisnya sudah ada, dia
     * anggap kodenya sudah pernah dikirim lalu melewatinya.
     *
     * Akibatnya, Owner/Mandor yang membuka halaman itu SEBELUM cron 06:30 membuat
     * seluruh baris kode terlanjur ada, dan cron kemudian melewati SEMUA karyawan
     * tanpa mengirim satu pesan pun — tanpa error apa pun yang muncul.
     */
    public static function kodeHariIniUntukJikaAda(User $user): ?string
    {
        return self::whereDate('tanggal', today())
                   ->where('user_id', $user->id)
                   ->first()?->kode;
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