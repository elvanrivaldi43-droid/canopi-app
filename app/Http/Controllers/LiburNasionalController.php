<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LiburNasional;
use App\Models\LiburNasionalPiket;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;

class LiburNasionalController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // KALENDER (semua level login — read-only kecuali Owner)
    // ═══════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $bulan = (int) ($request->query('bulan', now()->month));
        $tahun = (int) ($request->query('tahun', now()->year));

        $awal  = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        $liburBulanIni = LiburNasional::where('tanggal_mulai', '<=', $akhir->format('Y-m-d'))
            ->where('tanggal_selesai', '>=', $awal->format('Y-m-d'))
            ->orderBy('tanggal_mulai')
            ->get();

        $piketBulanIni = LiburNasionalPiket::whereIn('libur_nasional_id', $liburBulanIni->pluck('id'))
            ->with('user')
            ->get()
            ->groupBy(fn($p) => $p->tanggal->format('Y-m-d'));

        $liburSemua = LiburNasional::orderBy('tanggal_mulai', 'desc')->get();

        $karyawan = User::where('level', '!=', 1)
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get(['id', 'name', 'jabatan']);

        return view('libur-nasional.index', compact(
            'bulan', 'tahun', 'awal', 'akhir', 'liburBulanIni', 'piketBulanIni', 'liburSemua', 'karyawan'
        ));
    }

    // ═══════════════════════════════════════════════════════════
    // TAMBAH LIBUR NASIONAL (Owner)
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $request->validate([
            'nama'             => 'required|string|max:100',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $libur = LiburNasional::create([
            'nama'             => $request->nama,
            'tanggal_mulai'    => $request->tanggal_mulai,
            'tanggal_selesai'  => $request->tanggal_selesai,
            'dibuat_oleh'      => Auth::id(),
        ]);

        $this->broadcastLiburBaru($libur);

        return back()->with('success', "Libur nasional \"{$libur->nama}\" berhasil ditambahkan.");
    }

    // ═══════════════════════════════════════════════════════════
    // HAPUS LIBUR NASIONAL (Owner)
    // ═══════════════════════════════════════════════════════════

    public function destroy(LiburNasional $liburNasional)
    {
        $nama = $liburNasional->nama;
        $liburNasional->delete();

        return back()->with('success', "Libur nasional \"{$nama}\" dihapus.");
    }

    // ═══════════════════════════════════════════════════════════
    // TAMBAH PIKET (Owner)
    // ═══════════════════════════════════════════════════════════

    public function piketStore(Request $request, LiburNasional $liburNasional)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal' => 'required|date',
        ]);

        $tanggal = Carbon::parse($request->tanggal);
        if ($tanggal->lt($liburNasional->tanggal_mulai) || $tanggal->gt($liburNasional->tanggal_selesai)) {
            return back()->with('error', 'Tanggal piket harus dalam rentang libur nasional ini.');
        }

        $piket = LiburNasionalPiket::firstOrCreate([
            'libur_nasional_id' => $liburNasional->id,
            'user_id'           => $request->user_id,
            'tanggal'           => $request->tanggal,
        ]);

        $this->notifPiket($piket, $liburNasional);

        return back()->with('success', 'Piket berhasil ditambahkan.');
    }

    // ═══════════════════════════════════════════════════════════
    // BATALKAN PIKET (Owner)
    // ═══════════════════════════════════════════════════════════

    public function piketDestroy(LiburNasionalPiket $liburNasionalPiket)
    {
        $liburNasionalPiket->delete();

        return back()->with('success', 'Piket dibatalkan.');
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function broadcastLiburBaru(LiburNasional $libur): void
    {
        $penerima = User::whereNotNull('telegram_chat_id')->get();

        $pesan = "🎉 *LIBUR NASIONAL BARU*\n"
               . "{$libur->nama}\n"
               . "Tanggal: {$libur->labelRentang()}\n"
               . "---\n"
               . "Kode absen otomatis tidak dikirim di tanggal ini, kecuali kamu ditunjuk piket.";

        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id, $pesan);
        }
    }

    private function notifPiket(LiburNasionalPiket $piket, LiburNasional $libur): void
    {
        $piket->loadMissing('user');

        $pesan = "📌 *JADWAL PIKET*\n"
               . "Kamu piket tanggal {$piket->tanggal->translatedFormat('l, d F Y')}\n"
               . "({$libur->nama}) — tetap masuk kerja ya.";

        app(TelegramService::class)->kirim($piket->user->telegram_chat_id, $pesan);
    }
}
