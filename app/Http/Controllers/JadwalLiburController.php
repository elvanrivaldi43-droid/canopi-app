<?php
// FILE: app/Http/Controllers/JadwalLiburController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\IzinAbsen;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;

class JadwalLiburController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // FORM AJUKAN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function create()
    {
        $user       = Auth::user();
        $tanggalMin = today()->addDay()->format('Y-m-d');

        $svc = app(LiburService::class);
        [$jendelaAwal, $jendelaAkhir] = $svc->jendelaTukarSkip(now());

        $punyaLiburDefault = $user->hari_libur_default !== null;
        $tanggalKandidat   = $punyaLiburDefault
            ? $svc->tanggalKandidatLibur($user->hari_libur_default, $jendelaAwal, $jendelaAkhir)
            : [];

        return view('jadwal-libur.create', compact(
            'user', 'tanggalMin', 'punyaLiburDefault', 'tanggalKandidat', 'jendelaAwal', 'jendelaAkhir'
        ));
    }

    // ═══════════════════════════════════════════════════════════
    // SIMPAN AJUAN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'tanggal'      => 'required|date|after:today',
            'tanggal_baru' => 'required_if:jenis,tukar|nullable|date|after:today|different:tanggal',
            'jenis'        => 'required|in:tambah,batal,tukar',
            'alasan'       => 'nullable|string|max:500',
        ]);

        $svc = app(LiburService::class);
        [$jendelaAwal, $jendelaAkhir] = $svc->jendelaTukarSkip(now());
        $tanggal = Carbon::parse($request->tanggal);

        if (in_array($request->jenis, ['batal', 'tukar'])) {
            if ($user->hari_libur_default === null) {
                return back()->with('error', 'Kamu belum punya jadwal libur default, gak bisa ajukan Skip/Tukar.')->withInput();
            }
            if ($tanggal->dayOfWeek != $user->hari_libur_default) {
                return back()->with('error', 'Tanggal itu bukan hari libur default kamu.')->withInput();
            }
            if ($tanggal->lt($jendelaAwal) || $tanggal->gt($jendelaAkhir)) {
                return back()->with('error', 'Tanggal harus dalam sisa minggu ini atau minggu depan.')->withInput();
            }
        }

        if ($request->jenis === 'tukar') {
            $tanggalBaru = Carbon::parse($request->tanggal_baru);
            if ($tanggalBaru->lt($jendelaAwal) || $tanggalBaru->gt($jendelaAkhir)) {
                return back()->with('error', 'Tanggal pengganti harus dalam sisa minggu ini atau minggu depan.')->withInput();
            }
            if ($tanggalBaru->dayOfWeek == $user->hari_libur_default) {
                return back()->with('error', 'Tanggal pengganti harus hari yang normalnya kamu kerja.')->withInput();
            }
        }

        $tanggalBaruInput = $request->jenis === 'tukar' ? $request->tanggal_baru : null;

        $bentrok = JadwalLibur::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($request, $tanggalBaruInput) {
                $q->whereDate('tanggal', $request->tanggal)
                  ->orWhereDate('tanggal_baru', $request->tanggal);
                if ($tanggalBaruInput) {
                    $q->orWhereDate('tanggal', $tanggalBaruInput)
                      ->orWhereDate('tanggal_baru', $tanggalBaruInput);
                }
            })
            ->exists();

        if ($bentrok) {
            return back()->with('error', 'Tanggal yang kamu pilih bentrok sama ajuan lain yang masih berjalan.')->withInput();
        }

        $bentrokIzin = IzinAbsen::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($request, $tanggalBaruInput) {
                $q->whereDate('tanggal', $request->tanggal);
                if ($tanggalBaruInput) {
                    $q->orWhereDate('tanggal', $tanggalBaruInput);
                }
            })
            ->exists();

        if ($bentrokIzin) {
            return back()->with('error', 'Tanggal ini sudah ada ajuan izin/sakit/cuti yang masih berjalan.')->withInput();
        }

        $jadwal = JadwalLibur::create([
            'user_id'      => $user->id,
            'tanggal'      => $request->tanggal,
            'tanggal_baru' => $tanggalBaruInput,
            'jenis'        => $request->jenis,
            'alasan'       => $request->alasan,
            'status'       => 'pending',
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

        return back()->with('success', "Jadwal libur {$jadwalLibur->user->name} pada {$jadwalLibur->labelTanggal()} disetujui.");
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
                   . "Tanggal: {$jadwal->labelTanggal('l, d F Y')}\n"
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
               . "Tanggal: {$jadwal->labelTanggal('l, d F Y')}\n"
               . "---\n"
               . "Detail di: app.kanopibsd.co.id/jadwal-libur";
        app(TelegramService::class)->kirim($user->telegram_chat_id, $pesan);
    }
}
