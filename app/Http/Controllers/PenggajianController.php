<?php
// FILE: app/Http/Controllers/PenggajianController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\SlipGaji;
use App\Models\Kasbon;
use App\Models\PotonganInsidental;
use App\Models\TabunganKaryawan;
use App\Services\GajiService;

class PenggajianController extends Controller
{
    protected GajiService $gajiService;

    public function __construct(GajiService $gajiService)
    {
        $this->gajiService = $gajiService;
    }

    public function index()
    {
        $bulan  = request('bulan', now()->month);
        $tahun  = request('tahun', now()->year);
        $level  = request('level', 0);
        $search = request('search', '');

        $query = User::where('level', '!=', 1)->where('status', 'aktif');

        if ($level > 0) $query->where('level', $level);
        if ($search) $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('jabatan', 'like', "%{$search}%");
        });

        $karyawan = $query
            ->with(['slipGaji' => fn($q) => $q->where('bulan', $bulan)->where('tahun', $tahun)])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $totalSlip       = SlipGaji::where('bulan', $bulan)->where('tahun', $tahun)->get();
        $totalKewajiban  = $totalSlip->where('status','!=','dibayar')->sum('gaji_bersih');
        $totalSudahBayar = $totalSlip->where('status','dibayar')->sum('gaji_bersih');
        $perluKonfirmasi = $totalSlip->where('status','menunggu_konfirmasi')->count();
        $proyeksiUM      = User::where('level','!=',1)->where('status','aktif')->sum('uang_makan') * 15;

        return view('penggajian.index', compact(
            'karyawan','bulan','tahun',
            'totalKewajiban','totalSudahBayar','perluKonfirmasi','proyeksiUM'
        ));
    }

    public function generate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'periode' => 'required|in:uang_makan,gaji_bulanan',
            'bulan'   => 'required|integer|between:1,12',
            'tahun'   => 'required|integer|min:2024',
        ]);

        try {
            if ($request->periode === 'uang_makan') {
                $slip = $this->gajiService->generateUangMakan(
                    $request->user_id, $request->bulan, $request->tahun
                );
            } else {
                $slip = $this->gajiService->generateGajiBulanan(
                    $request->user_id, $request->bulan, $request->tahun
                );
            }
            return back()->with('success', "Slip berhasil digenerate untuk " . User::find($request->user_id)->name);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function generateSemua(Request $request)
    {
        $request->validate([
            'periode' => 'required|in:uang_makan,gaji_bulanan',
            'bulan'   => 'required|integer|between:1,12',
            'tahun'   => 'required|integer|min:2024',
        ]);

        $karyawan   = User::where('level', '!=', 1)->where('status', 'aktif')->get();
        $berhasil   = 0;
        $gagal      = 0;
        $pesanGagal = [];

        foreach ($karyawan as $k) {
            try {
                if ($request->periode === 'uang_makan') {
                    $this->gajiService->generateUangMakan($k->id, $request->bulan, $request->tahun);
                } else {
                    $this->gajiService->generateGajiBulanan($k->id, $request->bulan, $request->tahun);
                }
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $pesanGagal[] = "{$k->name}: {$e->getMessage()}";
            }
        }

        $pesan = "✅ {$berhasil} slip berhasil digenerate.";
        if ($gagal > 0) $pesan .= " ❌ {$gagal} gagal: " . implode(', ', $pesanGagal);

        return back()->with($gagal > 0 ? 'error' : 'success', $pesan);
    }

    public function show(SlipGaji $slip)
    {
        $slip->load('user.tunjangan');

        $absensiDetail = collect();
        if ($slip->periode === 'uang_makan') {
            $absensiDetail = \App\Models\Absensi::where('user_id', $slip->user_id)
                ->whereMonth('tanggal', $slip->bulan)
                ->whereYear('tanggal', $slip->tahun)
                ->whereDay('tanggal', '<=', 15)
                ->get()
                ->keyBy(fn($a) => $a->tanggal->format('Y-m-d'));
        }

        return view('penggajian.slip', compact('slip', 'absensiDetail'));
    }

    public function konfirmasi(SlipGaji $slip)
    {
        $slip->update(['owner_konfirmasi' => true, 'status' => 'draft']);
        return back()->with('success', 'Slip dikonfirmasi, siap untuk diproses.');
    }

    public function bayar(SlipGaji $slip)
    {
        try {
            $this->gajiService->prosesBayar($slip, Auth::id());

            $user = $slip->user;
            if ($user->no_hp) {
                $pesan = "💰 *SLIP GAJI*\n"
                       . "Hai {$user->name}!\n"
                       . "{$slip->periodeLabel()} {$slip->namaBulan()} {$slip->tahun}\n"
                       . "Gaji bersih: Rp " . number_format($slip->gaji_bersih, 0, ',', '.') . "\n"
                       . "---\n"
                       . "Lihat slip lengkap di: app.kanopibsd.co.id/penggajian/slip-saya";
                $this->kirimWA($user->no_hp, $pesan);
            }

            return back()->with('success', "Gaji {$slip->user->name} berhasil diproses.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function bayarSemua(Request $request)
    {
        $request->validate([
            'periode' => 'required|in:uang_makan,gaji_bulanan',
            'bulan'   => 'required|integer',
            'tahun'   => 'required|integer',
        ]);

        $slips = SlipGaji::where('periode', $request->periode)
                         ->where('bulan', $request->bulan)
                         ->where('tahun', $request->tahun)
                         ->where('status', 'draft')
                         ->get();

        $berhasil = 0;
        foreach ($slips as $slip) {
            try {
                $this->gajiService->prosesBayar($slip, Auth::id());
                $berhasil++;
            } catch (\Exception $e) {
                // skip yang error
            }
        }

        return back()->with('success', "✅ {$berhasil} slip berhasil dibayar.");
    }

    public function slipSaya()
    {
        $user    = Auth::user();
        $periode = request('periode');

        $query = SlipGaji::where('user_id', $user->id)
                         ->whereIn('status', ['dibayar', 'draft', 'menunggu_konfirmasi'])
                         ->orderBy('tahun', 'desc')
                         ->orderBy('bulan', 'desc');

        if ($periode) $query->where('periode', $periode);

        $slips = $query->get();

        return view('penggajian.slip-saya', compact('user', 'slips'));
    }

    public function kasbon()
    {
        $karyawan = User::where('level', '!=', 1)
                        ->where('status', 'aktif')
                        ->with(['kasbon' => fn($q) => $q->whereIn('status',['aktif','pending'])])
                        ->get();

        return view('penggajian.kasbon', compact('karyawan'));
    }

    public function kasbonStore(Request $request)
    {
        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'nominal'        => 'required|numeric|min:50000',
            'keterangan'     => 'required|string',
            'jumlah_cicilan' => 'required|integer|min:1|max:24',
        ]);

        $cicilan = $request->nominal / $request->jumlah_cicilan;

        Kasbon::create([
            'user_id'           => $request->user_id,
            'tanggal'           => today(),
            'nominal'           => $request->nominal,
            'keterangan'        => $request->keterangan,
            'cicilan_per_bulan' => $cicilan,
            'jumlah_cicilan'    => $request->jumlah_cicilan,
            'sisa_kasbon'       => $request->nominal,
            'status'            => 'aktif',
            'approved_oleh'     => Auth::id(),
        ]);

        return back()->with('success', 'Kasbon berhasil dicatat.');
    }

    public function kasbonTunda(Request $request, Kasbon $kasbon)
    {
        $request->validate([
            'tunda_sampai' => 'required|date|after:today',
        ]);

        $maxTunda = today()->addMonth();
        if ($request->tunda_sampai > $maxTunda->format('Y-m-d')) {
            return back()->with('error', 'Penundaan maksimal 1 bulan.');
        }

        $kasbon->update(['ditunda_sampai' => $request->tunda_sampai, 'status' => 'ditunda']);
        return back()->with('success', 'Cicilan kasbon ditunda.');
    }

    // ═══════════════════════════════════════════════════════
    // APPROVE KASBON (owner)
    // ═══════════════════════════════════════════════════════
    public function kasbonApprove(Request $request, Kasbon $kasbon)
    {
        if ($kasbon->status !== 'pending') {
            return back()->with('error', 'Kasbon ini sudah diproses sebelumnya.');
        }

        $kasbon->update([
            'status'        => 'aktif',
            'approved_oleh' => Auth::id(),
        ]);

        $karyawan = $kasbon->user;
        if ($karyawan?->no_hp) {
            $this->kirimWA($karyawan->no_hp,
                "✅ *KASBON DISETUJUI*\n" .
                "Hai {$karyawan->name}, kasbon kamu disetujui!\n" .
                "Nominal: Rp " . number_format($kasbon->nominal,0,',','.') . "\n" .
                "Cicilan: {$kasbon->jumlah_cicilan}x Rp " . number_format($kasbon->cicilan_per_bulan,0,',','.') . "/bulan\n" .
                "Cicilan mulai dipotong dari gaji bulan depan.\n" .
                "---\n" .
                "Detail: app.kanopibsd.co.id/kasbon-saya"
            );
        }

        return back()->with('success', "Kasbon {$karyawan->name} berhasil disetujui.");
    }

    // ═══════════════════════════════════════════════════════
    // TOLAK KASBON (owner)
    // ═══════════════════════════════════════════════════════
    public function kasbonTolak(Request $request, Kasbon $kasbon)
    {
        $request->validate(['alasan' => 'required|string|max:255']);

        if ($kasbon->status !== 'pending') {
            return back()->with('error', 'Kasbon ini sudah diproses sebelumnya.');
        }

        $kasbon->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $request->alasan,
            'ditolak_oleh' => Auth::id(),
            'ditolak_at'   => now(),
        ]);

        $karyawan = $kasbon->user;
        if ($karyawan?->no_hp) {
            $this->kirimWA($karyawan->no_hp,
                "❌ *KASBON DITOLAK*\n" .
                "Hai {$karyawan->name}, kasbon kamu ditolak.\n" .
                "Nominal: Rp " . number_format($kasbon->nominal,0,',','.') . "\n" .
                "Alasan: {$request->alasan}\n" .
                "---\n" .
                "Hubungi owner jika ada pertanyaan."
            );
        }

        return back()->with('success', "Kasbon {$karyawan->name} ditolak.");
    }

    public function insidentalStore(Request $request)
    {
        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'keterangan'     => 'required|string',
            'nominal_total'  => 'required|numeric|min:10000',
            'jumlah_cicilan' => 'required|integer|min:1|max:12',
        ]);

        $cicilan = $request->nominal_total / $request->jumlah_cicilan;

        PotonganInsidental::create([
            'user_id'           => $request->user_id,
            'keterangan'        => $request->keterangan,
            'nominal_total'     => $request->nominal_total,
            'jumlah_cicilan'    => $request->jumlah_cicilan,
            'cicilan_per_bulan' => $cicilan,
            'sisa'              => $request->nominal_total,
            'status'            => 'aktif',
            'input_oleh'        => Auth::id(),
            'tanggal_mulai'     => today(),
        ]);

        return back()->with('success', 'Potongan insidental berhasil dicatat.');
    }

    public function setTabunganLebaran(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'nominal' => 'required|numeric|min:0',
        ]);

        TabunganKaryawan::updateOrCreate(
            ['user_id' => $request->user_id],
            ['tabungan_lebaran_per_bulan' => $request->nominal]
        );

        return back()->with('success', 'Tabungan lebaran berhasil diupdate.');
    }

    private function kirimWA(string $noHp, string $pesan): void
    {
        $token = env('FONNTE_TOKEN', '');
        if (!$token) return;
        $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));
        $ch = curl_init();
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