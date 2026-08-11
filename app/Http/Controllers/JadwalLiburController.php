<?php
// FILE: app/Http/Controllers/JadwalLiburController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\User;
use App\Services\TelegramService;

class JadwalLiburController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // FORM AJUKAN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function create()
    {
        $user       = Auth::user();
        $tanggalMin = today()->addDay()->format('Y-m-d');

        return view('jadwal-libur.create', compact('user', 'tanggalMin'));
    }

    // ═══════════════════════════════════════════════════════════
    // SIMPAN AJUAN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'tanggal' => 'required|date|after:today',
            'jenis'   => 'required|in:tambah,batal',
            'alasan'  => 'nullable|string|max:500',
        ]);

        $sudahAda = JadwalLibur::where('user_id', $user->id)
                               ->whereDate('tanggal', $request->tanggal)
                               ->whereIn('status', ['pending', 'approved'])
                               ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Kamu sudah punya ajuan jadwal libur pada tanggal tersebut.');
        }

        $jadwal = JadwalLibur::create([
            'user_id' => $user->id,
            'tanggal' => $request->tanggal,
            'jenis'   => $request->jenis,
            'alasan'  => $request->alasan,
            'status'  => 'pending',
        ]);

        $this->kirimNotifPengajuan($user, $jadwal);

        return redirect()->route('jadwal-libur.index')
            ->with('success', 'Ajuan jadwal libur berhasil dikirim. Menunggu persetujuan Owner/Mandor.');
    }

    // ═══════════════════════════════════════════════════════════
    // RIWAYAT AJUAN SAYA (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function index()
    {
        $user       = Auth::user();
        $jadwalList = JadwalLibur::where('user_id', $user->id)
                                 ->orderBy('tanggal', 'desc')
                                 ->limit(30)
                                 ->get();

        return view('jadwal-libur.index', compact('user', 'jadwalList'));
    }

    // ═══════════════════════════════════════════════════════════
    // DAFTAR PENDING (Owner/Mandor)
    // ═══════════════════════════════════════════════════════════

    public function approval()
    {
        $pending = JadwalLibur::where('status', 'pending')
                              ->with('user')
                              ->orderBy('tanggal')
                              ->get();

        $riwayat = JadwalLibur::whereIn('status', ['approved', 'rejected'])
                              ->with('user')
                              ->orderBy('updated_at', 'desc')
                              ->limit(20)
                              ->get();

        return view('jadwal-libur.approval', compact('pending', 'riwayat'));
    }

    // ═══════════════════════════════════════════════════════════
    // APPROVE (Owner/Mandor)
    // ═══════════════════════════════════════════════════════════

    public function approve(Request $request, JadwalLibur $jadwalLibur)
    {
        $jadwalLibur->update([
            'status'        => 'approved',
            'diproses_oleh' => Auth::id(),
            'diproses_at'   => now(),
        ]);

        $this->kirimNotifHasil($jadwalLibur, 'approved');

        return back()->with('success', "Jadwal libur {$jadwalLibur->user->name} pada {$jadwalLibur->tanggal->format('d/m/Y')} disetujui.");
    }

    // ═══════════════════════════════════════════════════════════
    // REJECT (Owner/Mandor)
    // ═══════════════════════════════════════════════════════════

    public function reject(Request $request, JadwalLibur $jadwalLibur)
    {
        $jadwalLibur->update([
            'status'        => 'rejected',
            'diproses_oleh' => Auth::id(),
            'diproses_at'   => now(),
        ]);

        $this->kirimNotifHasil($jadwalLibur, 'rejected');

        return back()->with('success', "Jadwal libur {$jadwalLibur->user->name} ditolak.");
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function kirimNotifPengajuan(User $user, JadwalLibur $jadwal): void
    {
        $penerima = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();

        foreach ($penerima as $p) {
            $pesan = "🗓️ *AJUAN JADWAL LIBUR*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$jadwal->tanggal->format('d/m/Y')}\n"
                   . "Jenis: {$jadwal->jenisLabel()}\n"
                   . ($jadwal->alasan ? "Alasan: {$jadwal->alasan}\n" : '')
                   . "---\n"
                   . "Approve/tolak di: app.kanopibsd.co.id/jadwal-libur/approval";
            app(TelegramService::class)->kirim($p->telegram_chat_id, $pesan);
        }
    }

    private function kirimNotifHasil(JadwalLibur $jadwal, string $hasil): void
    {
        $user = $jadwal->user;

        $icon  = $hasil === 'approved' ? '✅' : '❌';
        $label = $hasil === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "{$icon} *JADWAL LIBUR {$label}*\n"
               . "Jenis: {$jadwal->jenisLabel()}\n"
               . "Tanggal: {$jadwal->tanggal->format('d/m/Y')}\n"
               . "---\n"
               . "Detail di: app.kanopibsd.co.id/jadwal-libur";
        app(TelegramService::class)->kirim($user->telegram_chat_id, $pesan);
    }
}
