<?php

namespace App\Http\Controllers;

use App\Models\LogBensin;
use App\Models\Kendaraan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogBensinController extends Controller
{
    // ─── INDEX — rekap semua log (owner + supervisor/mandor) ───────────
    public function index(Request $request)
    {
        $bulan  = $request->bulan  ?? now()->month;
        $tahun  = $request->tahun  ?? now()->year;
        $kendaraanId = $request->kendaraan_id ?? null;

        $query = LogBensin::with(['kendaraan', 'driver'])
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at');

        if ($kendaraanId) {
            $query->where('kendaraan_id', $kendaraanId);
        }

        $logs = $query->get();

        // Stats
        $totalLiter   = $logs->where('status','selesai')->sum('liter');
        $totalNominal = $logs->where('status','selesai')->sum('nominal');
        $totalKm      = $logs->where('status','selesai')->sum('km_tempuh');
        $rataKonsumsi = $totalLiter > 0 ? round($totalKm / $totalLiter, 2) : 0;

        $daftarKendaraan = Kendaraan::aktif()->get();

        return view('bensin.index', compact(
            'logs', 'bulan', 'tahun', 'kendaraanId',
            'totalLiter', 'totalNominal', 'totalKm', 'rataKonsumsi',
            'daftarKendaraan'
        ));
    }

    // ─── FORM INPUT BERANGKAT (driver) ─────────────────────────────────
    public function create()
    {
        $kendaraan = Kendaraan::aktif()->get();
        return view('bensin.create', compact('kendaraan'));
    }

    // ─── SIMPAN LOG BERANGKAT ──────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'kendaraan_id' => 'required|exists:kendaraan,id',
            'tujuan'       => 'required|string|max:255',
            'km_awal'      => 'required|numeric|min:0',
            'liter'        => 'required|numeric|min:0.1',
            'nominal'      => 'required|integer|min:1000',
            'tanggal'      => 'required|date',
            'catatan'      => 'nullable|string|max:255',
        ]);

        LogBensin::create([
            'kendaraan_id' => $request->kendaraan_id,
            'driver_id'    => Auth::id(),
            'tanggal'      => $request->tanggal,
            'tujuan'       => $request->tujuan,
            'km_awal'      => $request->km_awal,
            'liter'        => $request->liter,
            'nominal'      => $request->nominal,
            'status'       => 'berangkat',
            'catatan'      => $request->catatan,
        ]);

        return redirect()->route('bensin.riwayat')
            ->with('success', 'Log berangkat berhasil dicatat!');
    }

    // ─── FORM INPUT PULANG (driver isi km akhir) ───────────────────────
    public function pulang($id)
    {
        $log = LogBensin::with('kendaraan')
            ->where('driver_id', Auth::id())
            ->where('status', 'berangkat')
            ->findOrFail($id);

        return view('bensin.pulang', compact('log'));
    }

    // ─── SIMPAN KM AKHIR + HITUNG KONSUMSI ────────────────────────────
    public function pulangStore(Request $request, $id)
    {
        $log = LogBensin::with('kendaraan')
            ->where('driver_id', Auth::id())
            ->where('status', 'berangkat')
            ->findOrFail($id);

        $request->validate([
            'km_akhir' => 'required|numeric|min:' . $log->km_awal,
        ], [
            'km_akhir.min' => 'KM akhir harus lebih besar dari KM awal (' . $log->km_awal . ').',
        ]);

        $kmTempuh       = $request->km_akhir - $log->km_awal;
        $konsumsiAktual = $log->liter > 0 ? round($kmTempuh / $log->liter, 2) : 0;

        $log->update([
            'km_akhir'         => $request->km_akhir,
            'km_tempuh'        => $kmTempuh,
            'konsumsi_aktual'  => $konsumsiAktual,
            'status'           => 'selesai',
        ]);

        // Cek apakah boros → notif WA ke owner
        $standar = $log->kendaraan->standar_km_per_liter;
        if ($konsumsiAktual < $standar && !$log->notif_boros_terkirim) {
            $this->kirimNotifBoros($log, $konsumsiAktual, $standar);
            $log->update(['notif_boros_terkirim' => 1]);
        }

        return redirect()->route('bensin.riwayat')
            ->with('success', 'Log pulang berhasil dicatat! Konsumsi: ' . $konsumsiAktual . ' km/liter.');
    }

    // ─── RIWAYAT DRIVER (log milik sendiri) ───────────────────────────
    public function riwayat(Request $request)
    {
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        $logs = LogBensin::with('kendaraan')
            ->where('driver_id', Auth::id())
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get();

        // Cek ada log berangkat yang belum diisi pulang
        $logAktif = LogBensin::with('kendaraan')
            ->where('driver_id', Auth::id())
            ->where('status', 'berangkat')
            ->whereDate('tanggal', today())
            ->first();

        $totalLiter   = $logs->where('status','selesai')->sum('liter');
        $totalNominal = $logs->where('status','selesai')->sum('nominal');
        $totalKm      = $logs->where('status','selesai')->sum('km_tempuh');

        return view('bensin.riwayat', compact(
            'logs', 'logAktif', 'bulan', 'tahun',
            'totalLiter', 'totalNominal', 'totalKm'
        ));
    }

    // ─── MASTER KENDARAAN (owner saja) ────────────────────────────────
    public function kendaraan()
    {
        $daftar = Kendaraan::orderBy('nama')->get();
        return view('bensin.kendaraan', compact('daftar'));
    }

    public function kendaraanStore(Request $request)
    {
        $request->validate([
            'nama'                 => 'required|string|max:100',
            'plat'                 => 'required|string|max:20',
            'jenis'                => 'nullable|string|max:50',
            'standar_km_per_liter' => 'required|numeric|min:1',
        ]);

        Kendaraan::create($request->only('nama','plat','jenis','standar_km_per_liter'));

        return back()->with('success', 'Kendaraan berhasil ditambahkan!');
    }

    public function kendaraanUpdate(Request $request, $id)
    {
        $kendaraan = Kendaraan::findOrFail($id);
        $request->validate([
            'nama'                 => 'required|string|max:100',
            'plat'                 => 'required|string|max:20',
            'jenis'                => 'nullable|string|max:50',
            'standar_km_per_liter' => 'required|numeric|min:1',
        ]);

        $kendaraan->update($request->only('nama','plat','jenis','standar_km_per_liter'));
        return back()->with('success', 'Kendaraan berhasil diperbarui!');
    }

    public function kendaraanToggle($id)
    {
        $kendaraan = Kendaraan::findOrFail($id);
        $kendaraan->update(['is_active' => !$kendaraan->is_active]);
        return back()->with('success', 'Status kendaraan diperbarui.');
    }

    // ─── PRIVATE: kirim notif WA boros ke owner ───────────────────────
    private function kirimNotifBoros(LogBensin $log, float $aktual, float $standar): void
    {
        $owner = User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $driver   = $log->driver->name ?? '-';
        $kend     = $log->kendaraan->nama ?? '-';
        $plat     = $log->kendaraan->plat ?? '-';
        $tujuan   = $log->tujuan;
        $tanggal  = $log->tanggal->format('d/m/Y');
        $selisih  = round($standar - $aktual, 2);

        $pesan = "⛽ *PERINGATAN BBM BOROS*\n\n"
               . "Driver: {$driver}\n"
               . "Kendaraan: {$kend} ({$plat})\n"
               . "Tujuan: {$tujuan}\n"
               . "Tanggal: {$tanggal}\n\n"
               . "Konsumsi aktual: *{$aktual} km/liter*\n"
               . "Standar: {$standar} km/liter\n"
               . "Selisih: -{$selisih} km/liter\n\n"
               . "_Cek kondisi kendaraan atau cara mengemudi._\n\n"
               . "_CanopiBSD v2_";

        $this->kirimWA($owner->no_hp, $pesan);
    }

    private function kirimWA(string $noHp, string $pesan): void
    {
        try {
            $token = getenv('FONNTE_TOKEN');
            if (!$token) return;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
                CURLOPT_HTTPHEADER     => ["Authorization: {$token}"],
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // silent fail
        }
    }
}