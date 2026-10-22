<?php

namespace App\Http\Controllers;

use App\Models\LuarKota;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LuarKotaController extends Controller
{
    // ─── INDEX — daftar semua luar kota ────────────────────────────────
    public function index(Request $request)
    {
        $status = $request->status ?? 'aktif';

        $query = LuarKota::with(['karyawan', 'dibuatOleh'])
            ->orderByDesc('tanggal_mulai');

        if ($status !== 'semua') {
            $query->where('status', $status);
        }

        $daftar = $query->get();

        // Karyawan yang sedang luar kota hari ini
        $sedangLuarKota = LuarKota::with('karyawan')
            ->aktifPadaTanggal()
            ->get();

        // Ajuan kerja di luar kantor; 7 hari ke belakang cukup (yang lewat tampil "Kedaluwarsa")
        $ajuanMenunggu = LuarKota::with('karyawan')
            ->where('status', 'menunggu')
            ->whereDate('tanggal_mulai', '>=', today()->subDays(7))
            ->orderByDesc('tanggal_mulai')
            ->get();

        return view('luar-kota.index', compact('daftar', 'status', 'sedangLuarKota', 'ajuanMenunggu'));
    }

    // ─── CREATE — form aktifkan luar kota ──────────────────────────────
    public function create()
    {
        // Admin Ops (2) & Toko Besi (7) hanya Owner yang bisa mengaktifkan (keputusan Elvan 21 Okt)
        $owner    = (int) Auth::user()->level === 1;
        $karyawan = User::where('status', 'aktif')
            ->whereIn('level', $owner ? [2, 3, 4, 5, 6, 7] : [3, 4, 5, 6])
            ->where('id', '!=', $owner ? 0 : Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'jabatan', 'level']);

        return view('luar-kota.create', compact('karyawan'));
    }

    // ─── STORE — simpan + notif WA ─────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'user_ids'        => 'required|array|min:1',
            'user_ids.*'      => 'exists:users,id',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'lokasi'          => 'required|string|max:255',
            'keterangan'      => 'nullable|string',
        ]);

        $levelTarget = User::whereIn('id', $request->user_ids)->pluck('level')->all();
        if ($alasan = LuarKota::alasanTolakBuatManual($request->user_ids, $levelTarget, (int) Auth::id(), (int) Auth::user()->level)) {
            return back()->withInput()->with('error', $alasan);
        }

        foreach ($request->user_ids as $userId) {
            // Cek kalau sudah ada luar kota aktif di rentang yang sama
            $existing = LuarKota::where('user_id', $userId)
                ->where('status', 'aktif')
                ->where('tanggal_mulai', '<=', $request->tanggal_selesai)
                ->where('tanggal_selesai', '>=', $request->tanggal_mulai)
                ->first();

            if ($existing) {
                // Update yang sudah ada
                $existing->update([
                    'tanggal_mulai'   => $request->tanggal_mulai,
                    'tanggal_selesai' => $request->tanggal_selesai,
                    'lokasi'          => $request->lokasi,
                    'keterangan'      => $request->keterangan,
                ]);
                $luarKota = $existing;
            } else {
                $luarKota = LuarKota::create([
                    'user_id'         => $userId,
                    'dibuat_oleh'     => Auth::id(),
                    'tanggal_mulai'   => $request->tanggal_mulai,
                    'tanggal_selesai' => $request->tanggal_selesai,
                    'lokasi'          => $request->lokasi,
                    'keterangan'      => $request->keterangan,
                    'status'          => 'aktif',
                ]);
            }

            // Notif WA ke karyawan
            $karyawan = User::find($userId);
            if ($karyawan) {
                $this->kirimNotifAktif($karyawan, $luarKota);
            }
        }

        return redirect()->route('luar-kota.index')
            ->with('success', 'Mode luar kota berhasil diaktifkan!');
    }

    // ─── EDIT — form edit rentang tanggal ──────────────────────────────
    public function edit($id)
    {
        $luarKota = LuarKota::with('karyawan')->findOrFail($id);
        abort_unless(LuarKota::bisaDiubahManual((string) $luarKota->status), 404);
        abort_if(LuarKota::alasanTolakBuatManual([$luarKota->user_id], [$luarKota->karyawan?->level], (int) Auth::id(), (int) Auth::user()->level), 403);
        return view('luar-kota.edit', compact('luarKota'));
    }

    // ─── UPDATE — perpanjang / perpendek tanggal ───────────────────────
    public function update(Request $request, $id)
    {
        $luarKota = LuarKota::with('karyawan')->findOrFail($id);
        abort_unless(LuarKota::bisaDiubahManual((string) $luarKota->status), 404);
        abort_if(LuarKota::alasanTolakBuatManual([$luarKota->user_id], [$luarKota->karyawan?->level], (int) Auth::id(), (int) Auth::user()->level), 403);

        $request->validate([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'lokasi'          => 'required|string|max:255',
            'keterangan'      => 'nullable|string',
        ]);

        $luarKota->update([
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'lokasi'          => $request->lokasi,
            'keterangan'      => $request->keterangan,
        ]);

        // Notif WA update
        $karyawan = $luarKota->karyawan;
        if ($karyawan) {
            $this->kirimNotifUpdate($karyawan, $luarKota);
        }

        return redirect()->route('luar-kota.index')
            ->with('success', 'Data luar kota berhasil diperbarui!');
    }

    // ─── SELESAIKAN — tandai selesai lebih awal ────────────────────────
    public function selesai($id)
    {
        $luarKota = LuarKota::findOrFail($id);
        abort_unless(LuarKota::bisaDiubahManual((string) $luarKota->status), 404);
        $luarKota->update([
            'status'          => 'selesai',
            'tanggal_selesai' => today(),
        ]);

        return back()->with('success', 'Mode luar kota diselesaikan.');
    }

    // ─── BATALKAN ──────────────────────────────────────────────────────
    public function batalkan($id)
    {
        $luarKota = LuarKota::findOrFail($id);
        abort_unless(LuarKota::bisaDiubahManual((string) $luarKota->status), 404);
        $luarKota->update(['status' => 'dibatalkan']);

        return back()->with('success', 'Mode luar kota dibatalkan.');
    }

    // ─── AJUAN KERJA DI LUAR KANTOR: setujui / tolak ───────────────────
    public function setujui($id)
    {
        $lk = LuarKota::with('karyawan')->findOrFail($id);
        if ($alasan = LuarKota::alasanTolakProses($lk->status, $lk->tanggal_mulai->toDateString(), (int) $lk->user_id, (int) Auth::id(), today()->toDateString(), (int) $lk->karyawan?->level, (int) Auth::user()->level)) {
            return back()->with('error', $alasan);
        }

        $lk->update(['status' => 'aktif', 'dibuat_oleh' => Auth::id()]);

        app(TelegramService::class)->kirim($lk->karyawan?->telegram_chat_id,
            "✅ *AJUAN KERJA DI LUAR KANTOR DISETUJUI*\n"
            . "Tujuan: {$lk->lokasi}\n"
            . "Hari ini lapor siang, lanjut kerja, dan absen pulang bebas lokasi (GPS tetap dicatat).");

        return back()->with('success', "Ajuan {$lk->karyawan?->name} disetujui.");
    }

    public function tolak(Request $request, $id)
    {
        $request->validate(['alasan' => 'required|string|max:255']);

        $lk = LuarKota::with('karyawan')->findOrFail($id);
        if ($alasan = LuarKota::alasanTolakProses($lk->status, $lk->tanggal_mulai->toDateString(), (int) $lk->user_id, (int) Auth::id(), today()->toDateString(), (int) $lk->karyawan?->level, (int) Auth::user()->level)) {
            return back()->with('error', $alasan);
        }

        $lk->update(['status' => 'ditolak', 'dibuat_oleh' => Auth::id(), 'keterangan' => $request->alasan]);

        app(TelegramService::class)->kirim($lk->karyawan?->telegram_chat_id,
            "❌ *AJUAN KERJA DI LUAR KANTOR DITOLAK*\n"
            . "Tujuan: {$lk->lokasi}\n"
            . "Alasan: {$request->alasan}");

        return back()->with('success', "Ajuan {$lk->karyawan?->name} ditolak.");
    }

    // ─── AUTO-UPDATE STATUS: tandai selesai kalau tanggal sudah lewat ──
    // Dipanggil dari cron atau setiap kali index dibuka
    public static function autoUpdateStatus(): void
    {
        LuarKota::where('status', 'aktif')
            ->where('tanggal_selesai', '<', today())
            ->update(['status' => 'selesai']);
    }

    // ─── PRIVATE: notif WA aktif ───────────────────────────────────────
    private function kirimNotifAktif(User $karyawan, LuarKota $lk): void
    {
        $mulai   = $lk->tanggal_mulai->format('d/m/Y');
        $selesai = $lk->tanggal_selesai->format('d/m/Y');
        $durasi  = $lk->durasiHari();

        $pesan = "✈️ *MODE LUAR KOTA DIAKTIFKAN*\n\n"
               . "Halo {$karyawan->name}!\n\n"
               . "Kamu telah didaftarkan sebagai *Luar Kota*:\n\n"
               . "📍 Lokasi: {$lk->lokasi}\n"
               . "📅 Mulai: {$mulai}\n"
               . "📅 Selesai: {$selesai}\n"
               . "🗓️ Durasi: {$durasi} hari\n\n"
               . "Selama periode ini, absensi kamu tetap berjalan normal.\n"
               . "GPS akan dicatat tapi tidak mempengaruhi validasi.\n\n"
               . "_CanopiBSD v2_";

        app(TelegramService::class)->kirim($karyawan->telegram_chat_id, $pesan);
    }

    private function kirimNotifUpdate(User $karyawan, LuarKota $lk): void
    {
        $mulai   = $lk->tanggal_mulai->format('d/m/Y');
        $selesai = $lk->tanggal_selesai->format('d/m/Y');

        $pesan = "✈️ *UPDATE LUAR KOTA*\n\n"
               . "Halo {$karyawan->name}!\n\n"
               . "Jadwal luar kota kamu diperbarui:\n\n"
               . "📍 Lokasi: {$lk->lokasi}\n"
               . "📅 Mulai: {$mulai}\n"
               . "📅 Selesai: {$selesai}\n\n"
               . "_CanopiBSD v2_";

        app(TelegramService::class)->kirim($karyawan->telegram_chat_id, $pesan);
    }
}