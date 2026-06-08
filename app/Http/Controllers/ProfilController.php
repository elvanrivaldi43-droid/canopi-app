<?php
// FILE: app/Http/Controllers/ProfilController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Absensi;

class ProfilController extends Controller
{
    public function index()
    {
        $user     = Auth::user();
        $bulanIni = Absensi::where('user_id', $user->id)
                           ->whereMonth('tanggal', now()->month)
                           ->whereYear('tanggal', now()->year)
                           ->get();

        $hariHadir  = $bulanIni->whereIn('status',['hadir','telat','setengah_hari'])->count();
        $hariAlpha  = $bulanIni->where('status','alpha')->count();
        $hariTelat  = $bulanIni->where('status','telat')->count();
        $hariKerja  = $this->hitungHariKerja(now()->month, now()->year);
        $persenHadir= $hariKerja > 0 ? ($hariHadir/$hariKerja)*100 : 0;

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
            'hadir'      => $hariHadir,
            'alpha'      => $hariAlpha,
            'telat'      => $hariTelat,
            'kelas_kpi'  => $kelasKpi,
            'total_gaji' => $bulanIni->sum('gaji_hari_ini'),
        ];

        return view('profil.index', compact('user', 'stats'));
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

    private function hitungHariKerja(int $bulan, int $tahun): int
    {
        $hariKerja = 0;
        $hariAkhir = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        for ($i = 1; $i <= now()->day; $i++) {
            $tgl = \Carbon\Carbon::createFromDate($tahun, $bulan, $i);
            if ($tgl->dayOfWeek !== \Carbon\Carbon::SUNDAY) $hariKerja++;
        }
        return $hariKerja;
    }
}