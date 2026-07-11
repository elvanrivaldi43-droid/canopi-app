<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Absensi;
use App\Models\IzinAbsen;
use App\Models\SlipGaji;
use App\Models\Kasbon;
use App\Models\User;
use App\Models\TugasAssignee;

class DashboardController extends Controller
{
    public function owner()
    {
        $tanggal = today();
        $bulan   = now()->month;
        $tahun   = now()->year;

        // Greeting
        $jam = (int)now()->format('H');
        $greeting = match(true) {
            $jam < 11  => 'Pagi',
            $jam < 15  => 'Siang',
            $jam < 18  => 'Sore',
            default    => 'Malam',
        };

        // Absensi hari ini
        $totalKaryawan  = User::where('level','!=',1)->where('status','aktif')->count();
        $absensiHariIni = Absensi::whereDate('tanggal',$tanggal)->with('user')->get();

        $hadir       = $absensiHariIni->whereIn('status',['hadir','telat','setengah_hari'])->count();
        $alpha       = $absensiHariIni->where('status','alpha')->count();
        $izinHariIni = $absensiHariIni->whereIn('status',['sakit','izin','cuti','dinas_luar'])->count();
        $belum       = max(0, $totalKaryawan - $hadir - $alpha - $izinHariIni);

        $listAlpha = User::where('level','!=',1)
                         ->where('status','aktif')
                         ->whereHas('absensi', fn($q) => $q->whereDate('tanggal',$tanggal)->where('status','alpha'))
                         ->get();

        $listBelumPulang = User::where('level','!=',1)
                               ->where('status','aktif')
                               ->whereHas('absensi', fn($q) => $q->whereDate('tanggal',$tanggal)->whereNotNull('jam_masuk')->whereNull('jam_pulang')->where('status','!=','alpha'))
                               ->with(['absensi' => fn($q) => $q->whereDate('tanggal',$tanggal)])
                               ->get();

        $absensi = [
            'hadir'             => $hadir,
            'alpha'             => $alpha,
            'izin'              => $izinHariIni,
            'belum'             => $belum,
            'list_alpha'        => $listAlpha,
            'list_belum_pulang' => $listBelumPulang,
        ];

        // Pending
        $pending = [
            'izin' => IzinAbsen::where('status','pending')->count(),
            'slip' => SlipGaji::where('status','menunggu_konfirmasi')->count(),
        ];

        // Keuangan
        $slipBulanIni = SlipGaji::where('bulan',$bulan)->where('tahun',$tahun)->get();
        $keuangan = [
            'kewajiban_gaji' => $slipBulanIni->whereNotIn('status',['dibayar'])->sum('gaji_bersih'),
            'sudah_bayar'    => $slipBulanIni->where('status','dibayar')->sum('gaji_bersih'),
            'proyeksi_um'    => User::where('level','!=',1)->where('status','aktif')->sum('uang_makan') * 15,
            'kasbon_aktif'   => Kasbon::where('status','aktif')->sum('sisa_kasbon'),
        ];

        // Laporan Keuangan (placeholder)
        $laporanKeuangan = ['pemasukan'=>0,'pengeluaran'=>0,'profit'=>0];

        // Project (placeholder)
        $project = ['aktif'=>0,'selesai'=>0,'pending'=>0,'nilai_bulan_ini'=>0,'nilai_total'=>0];

        // Leads & Survei (placeholder)
        $leads = ['bulan_ini'=>0,'total'=>0,'survei_pending'=>0,'closing_rate'=>0,'deal_bulan_ini'=>0];

        // SDM bulan ini
        $absenBulanIni = Absensi::whereMonth('tanggal',$bulan)->whereYear('tanggal',$tahun)->get();
        $sdm = [
            'total_karyawan' => $totalKaryawan,
            'total_alpha'    => $absenBulanIni->where('status','alpha')->count(),
            'total_telat'    => $absenBulanIni->where('status','telat')->count(),
        ];

        return view('dashboard.owner', compact(
            'greeting','absensi','pending','keuangan',
            'laporanKeuangan','project','leads','sdm'
        ));
    }

    public function admin()
    {
        $tugasHariIni = TugasAssignee::with('tugas')
            ->where('user_id', Auth::id())
            ->whereHas('tugas', fn($q) => $q->whereDate('tanggal', today()))
            ->get();

        return view('dashboard.admin', compact('tugasHariIni'));
    }

    public function supervisor()
    {
        $tugasHariIni = TugasAssignee::with('tugas')
            ->where('user_id', Auth::id())
            ->whereHas('tugas', fn($q) => $q->whereDate('tanggal', today()))
            ->get();

        return view('dashboard.supervisor', compact('tugasHariIni'));
    }

    public function marketing()
    {
        $tugasHariIni = TugasAssignee::with('tugas')
            ->where('user_id', Auth::id())
            ->whereHas('tugas', fn($q) => $q->whereDate('tanggal', today()))
            ->get();

        return view('dashboard.marketing', compact('tugasHariIni'));
    }

    public function teknisi()
    {
        $tugasHariIni = TugasAssignee::with('tugas')
            ->where('user_id', Auth::id())
            ->whereHas('tugas', fn($q) => $q->whereDate('tanggal', today()))
            ->get();

        return view('dashboard.teknisi', compact('tugasHariIni'));
    }

    public function driver()
    {
        $tugasHariIni = TugasAssignee::with('tugas')
            ->where('user_id', Auth::id())
            ->whereHas('tugas', fn($q) => $q->whereDate('tanggal', today()))
            ->get();

        return view('dashboard.driver', compact('tugasHariIni'));
    }

    public function toko()
    {
        return view('dashboard.toko');
    }
}