<?php
// FILE: app/Http/Controllers/ProfilController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Absensi;
use App\Models\User;
use App\Services\LiburService;
use App\Services\KerjaHariLiburService;

class ProfilController extends Controller
{
    public function index()
    {
        $user     = Auth::user();

        if (!$user->telegram_chat_id && !$user->telegram_link_token) {
            $user->telegram_link_token = $this->generateTelegramLinkToken();
            $user->save();
        }

        $botUsername = getenv('TELEGRAM_KARYAWAN_BOT_USERNAME') ?: '';

        $bulanIni = Absensi::where('user_id', $user->id)
                           ->whereMonth('tanggal', now()->month)
                           ->whereYear('tanggal', now()->year)
                           ->get();

        // Hari libur yang DIAKTIFKAN dihitung sebagai hari kerja biasa: tanggal itu
        // masuk penyebut (LiburService::hitungHariKerja) DAN pembilang di sini.
        // Jangan dikembalikan ke rancangan lama yang membuang baris aktivasi dari
        // statistik — itu bikin karyawan yang diaktifkan lalu MANGKIR hilang dari
        // laporan (alpha tidak terhitung, KPI tidak turun). Upah ekstra hari libur
        // tetap dipisah lewat `kerja_libur`/`upah_libur`, bukan lewat statistik ini.
        $svcLibur   = app(KerjaHariLiburService::class);
        $statLibur  = $svcLibur->statistikKehadiran($bulanIni);

        $hariHadir  = $statLibur['hadir'];
        $hariAlpha  = $statLibur['alpha'];
        $hariTelat  = $statLibur['telat'];
        $hariKerja  = app(LiburService::class)->hitungHariKerja($user, now()->month, now()->year, now()->day);
        $persenHadir= $svcLibur->persenHadir($hariHadir, $hariKerja);

        // Hitung kelas KPI
        $kelasKpi = 'Belum';
        if ($hariAlpha == 0) {
            if ($hariTelat == 0 && $persenHadir >= 100) $kelasKpi = '🏆 Platinum';
            elseif ($hariTelat <= 1 && $persenHadir >= 90) $kelasKpi = '🥇 Gold';
            elseif ($hariTelat <= 2 && $persenHadir >= 80) $kelasKpi = '🥈 Silver';
        } else {
            $kelasKpi = '❌ Gugur';
        }

        $stats = [
            'hadir'       => $hariHadir,
            'alpha'       => $hariAlpha,
            'telat'       => $hariTelat,
            'kelas_kpi'   => $kelasKpi,
            'kerja_libur' => $statLibur['kerja_libur'],
            'total_gaji'  => $bulanIni->sum('gaji_hari_ini'),
        ];

        return view('profil.index', compact('user', 'stats', 'botUsername'));
    }

    private function generateTelegramLinkToken(): string
    {
        do {
            $token = Str::random(32);
        } while (User::where('telegram_link_token', $token)->exists());

        return $token;
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'no_hp'                => 'nullable|string|max:20',
            'alamat'               => 'nullable|string|max:500',
            'nama_kontak_darurat'  => 'nullable|string|max:100',
            'no_kontak_darurat'    => 'nullable|string|max:20',
        ]);

        $user->update([
            'no_hp'               => $request->no_hp,
            'alamat'              => $request->alamat,
            'nama_kontak_darurat' => $request->nama_kontak_darurat,
            'no_kontak_darurat'   => $request->no_kontak_darurat,
        ]);

        return back()->with('success', 'Profil berhasil diupdate!');
    }
}