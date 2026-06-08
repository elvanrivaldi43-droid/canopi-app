<?php
// FILE: app/Http/Controllers/IzinAbsenController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\IzinAbsen;
use App\Models\Absensi;
use App\Models\User;

class IzinAbsenController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // FORM AJUKAN IZIN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function create()
    {
        $user     = Auth::user();
        $tipeList = IzinAbsen::TIPE;

        // Cek apakah sudah ada izin pending untuk besok
        $sudahAda = IzinAbsen::where('user_id', $user->id)
                             ->whereDate('tanggal', today()->addDay())
                             ->where('status', 'pending')
                             ->exists();

        // Batas waktu pengajuan izin H-1 jam 22:00
        $bisaAjukan = now()->format('H:i') <= '22:00';
        $tanggalMin = today()->addDay()->format('Y-m-d');
        $tanggalMax = today()->addDays(30)->format('Y-m-d');

        return view('izin.create', compact('user','tipeList','sudahAda','bisaAjukan','tanggalMin','tanggalMax'));
    }

    // ═══════════════════════════════════════════════════════════
    // SIMPAN IZIN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $user = Auth::user();

        // Cek jam pengajuan
        if (now()->format('H:i') > '22:00') {
            return back()->with('error', 'Pengajuan izin sudah tutup. Batas pengajuan jam 22:00.');
        }

        $request->validate([
            'tipe'       => 'required|in:sakit,izin,cuti,dinas_luar',
            'tanggal'    => 'required|date|after:today',
            'alasan'     => 'required|string|max:500',
            'foto_surat' => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
        ], [
            'tanggal.after' => 'Tanggal izin harus H+1 atau lebih',
        ]);

        // Cek cuti harus min H-3
        if ($request->tipe === 'cuti') {
            $selisih = now()->diffInDays($request->tanggal);
            if ($selisih < 3) {
                return back()->with('error', 'Pengajuan cuti minimal H-3 (3 hari sebelumnya).');
            }
        }

        // Cek duplikat
        $sudahAda = IzinAbsen::where('user_id', $user->id)
                             ->whereDate('tanggal', $request->tanggal)
                             ->whereIn('status', ['pending','approved'])
                             ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Kamu sudah punya izin pada tanggal tersebut.');
        }

        // Upload foto surat jika ada
        $fotoPath = null;
        if ($request->hasFile('foto_surat')) {
            $fotoPath = $request->file('foto_surat')->store('izin/' . $user->id, 'public');
        }

        // Sakit langsung approved, lainnya pending
        $status = $request->tipe === 'sakit' ? 'approved' : 'pending';

        $izin = IzinAbsen::create([
            'user_id'    => $user->id,
            'tanggal'    => $request->tanggal,
            'tipe'       => $request->tipe,
            'alasan'     => $request->alasan,
            'foto_surat' => $fotoPath,
            'status'     => $status,
        ]);

        // Update absensi jika sakit (langsung approved)
        if ($status === 'approved') {
            $this->updateAbsensiDariIzin($izin);
        }

        // Notif WA ke mandor & owner (kecuali sakit langsung approved)
        if ($status === 'pending') {
            $this->kirimNotifPengajuan($user, $izin);
        }

        $pesan = $status === 'approved'
            ? "✅ Izin sakit berhasil diajukan dan langsung disetujui."
            : "✅ Pengajuan {$izin->tipeLabel()} berhasil dikirim. Menunggu persetujuan mandor/owner.";

        return redirect()->route('izin.index')->with('success', $pesan);
    }

    // ═══════════════════════════════════════════════════════════
    // DAFTAR IZIN SAYA (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function index()
    {
        $user   = Auth::user();
        $izinList = IzinAbsen::where('user_id', $user->id)
                             ->orderBy('tanggal', 'desc')
                             ->limit(30)
                             ->get();

        return view('izin.index', compact('user','izinList'));
    }

    // ═══════════════════════════════════════════════════════════
    // DAFTAR IZIN PENDING (mandor/owner)
    // ═══════════════════════════════════════════════════════════

    public function approval()
    {
        $pending = IzinAbsen::where('status', 'pending')
                            ->with('user')
                            ->orderBy('tanggal')
                            ->get();

        $riwayat = IzinAbsen::whereIn('status', ['approved','rejected'])
                            ->with('user')
                            ->orderBy('updated_at', 'desc')
                            ->limit(20)
                            ->get();

        return view('izin.approval', compact('pending','riwayat'));
    }

    // ═══════════════════════════════════════════════════════════
    // APPROVE IZIN (mandor/owner)
    // ═══════════════════════════════════════════════════════════

    public function approve(Request $request, IzinAbsen $izin)
    {
        $request->validate([
            'catatan' => 'nullable|string|max:255',
        ]);

        $izin->update([
            'status'        => 'approved',
            'catatan_mandor'=> $request->catatan,
            'diproses_oleh' => Auth::id(),
            'diproses_at'   => now(),
        ]);

        // Update absensi
        $this->updateAbsensiDariIzin($izin);

        // Notif ke karyawan
        $this->kirimNotifHasilIzin($izin, 'approved');

        return back()->with('success', "Izin {$izin->user->name} pada {$izin->tanggal->format('d/m/Y')} telah disetujui.");
    }

    // ═══════════════════════════════════════════════════════════
    // REJECT IZIN (mandor/owner)
    // ═══════════════════════════════════════════════════════════

    public function reject(Request $request, IzinAbsen $izin)
    {
        $request->validate([
            'catatan' => 'required|string|max:255',
        ]);

        $izin->update([
            'status'        => 'rejected',
            'catatan_mandor'=> $request->catatan,
            'diproses_oleh' => Auth::id(),
            'diproses_at'   => now(),
        ]);

        // Notif ke karyawan
        $this->kirimNotifHasilIzin($izin, 'rejected');

        return back()->with('success', "Izin {$izin->user->name} telah ditolak.");
    }

    // ═══════════════════════════════════════════════════════════
    // DINAS LUAR — input oleh mandor/owner
    // ═══════════════════════════════════════════════════════════

    public function dinasLuar(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'tanggal'  => 'required|date',
            'alasan'   => 'required|string|max:500',
        ]);

        $izin = IzinAbsen::create([
            'user_id'        => $request->user_id,
            'tanggal'        => $request->tanggal,
            'tipe'           => 'dinas_luar',
            'alasan'         => $request->alasan,
            'status'         => 'approved',
            'diproses_oleh'  => Auth::id(),
            'diproses_at'    => now(),
        ]);

        $this->updateAbsensiDariIzin($izin);

        // Notif ke karyawan
        $karyawan = User::find($request->user_id);
        if ($karyawan?->no_hp) {
            $pesan = "📋 *INFO DINAS LUAR*\n"
                   . "Kamu dijadwalkan dinas luar pada ".($izin->tanggal->format('d/m/Y'))."\n"
                   . "Keterangan: {$request->alasan}\n"
                   . "GPS absen bebas pada hari tersebut.";
            $this->kirimWA($karyawan->no_hp, $pesan);
        }

        return back()->with('success', 'Dinas luar berhasil dicatat.');
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function updateAbsensiDariIzin(IzinAbsen $izin): void
    {
        $user = $izin->user;

        // Buat/update record absensi
        Absensi::updateOrCreate(
            ['user_id' => $izin->user_id, 'tanggal' => $izin->tanggal],
            [
                'status'              => $izin->tipe, // sakit, izin, cuti, dinas_luar
                'keterangan'          => $izin->alasan,
                'uang_makan_hari_ini' => $user->uang_makan ?? 0, // semua dapat UM penuh
                'gaji_hari_ini'       => match($izin->tipe) {
                    'dinas_luar' => 0, // diliburkan = gaji 0
                    default      => $user->gaji_harian ?? 0, // sakit/izin/cuti = gaji penuh
                },
            ]
        );
    }

    private function kirimNotifPengajuan(User $user, IzinAbsen $izin): void
    {
        // Kirim ke mandor (level 3) dan owner (level 1)
        $penerima = User::whereIn('level', [1, 3])->whereNotNull('no_hp')->get();

        foreach ($penerima as $p) {
            $pesan = "📋 *PENGAJUAN {$izin->tipeLabel()}*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$izin->tanggal->format('d/m/Y')}\n"
                   . "Alasan: {$izin->alasan}\n"
                   . "---\n"
                   . "Approve/tolak di: app.kanopibsd.co.id/izin/approval";
            $this->kirimWA($p->no_hp, $pesan);
        }
    }

    private function kirimNotifHasilIzin(IzinAbsen $izin, string $hasil): void
    {
        $user = $izin->user;
        if (!$user->no_hp) return;

        $icon  = $hasil === 'approved' ? '✅' : '❌';
        $label = $hasil === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "{$icon} *IZIN {$label}*\n"
               . "Tipe: {$izin->tipeLabel()}\n"
               . "Tanggal: {$izin->tanggal->format('d/m/Y')}\n"
               . ($izin->catatan_mandor ? "Catatan: {$izin->catatan_mandor}\n" : '')
               . "---\n"
               . "Detail di: app.kanopibsd.co.id/izin";
        $this->kirimWA($user->no_hp, $pesan);
    }

    private function kirimWA(string $noHp, string $pesan): void
    {
        $token = env('FONNTE_TOKEN', '');
        if (!$token) return;

        $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));
        $ch   = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}