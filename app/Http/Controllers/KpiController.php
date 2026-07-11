<?php

namespace App\Http\Controllers;

use App\Models\KpiKinerja;
use App\Models\SpKaryawan;
use App\Models\RaporKaryawan;
use App\Models\UjianSoal;
use App\Models\UjianSesi;
use App\Models\UjianJawaban;
use App\Models\User;
use App\Services\KpiService;
use App\Services\FonnteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiController extends Controller
{
    // ============================================================
    // DASHBOARD KPI — Owner melihat semua karyawan
    // ============================================================
    public function index(Request $request)
    {
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        // Semua KPI bulan ini
        $kpiList = KpiKinerja::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->with('user')
            ->orderByDesc('total_poin')
            ->get();

        // Bintang jabatan bulan lalu (untuk ditampilkan di dashboard)
        $bulanTampil = now()->month - 1 ?: 12;
        $tahunTampil = now()->month - 1 ? now()->year : now()->year - 1;
        $bintangBulanLalu = KpiKinerja::where('bulan', $bulanTampil)
            ->where('tahun', $tahunTampil)
            ->where('is_bintang_jabatan', 1)
            ->with('user')
            ->get();

        // Usulan SP yang menunggu konfirmasi
        $usulanSp = SpKaryawan::where('status', 'usulan')
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        // Statistik bulan ini
        $stats = [
            'total'    => $kpiList->count(),
            'bintang5' => $kpiList->where('bintang', 5)->where('is_alpha', 0)->count(),
            'bintang4' => $kpiList->where('bintang', 4)->count(),
            'red_zone' => $kpiList->where('total_poin', '<', 45)->count(),
            'avg_poin' => round($kpiList->avg('total_poin'), 1),
        ];

        // Daftar bulan untuk filter
        $bulanOptions = [];
        for ($i = 11; $i >= 0; $i--) {
            $dt = Carbon::now()->subMonths($i);
            $bulanOptions[] = ['bulan' => $dt->month, 'tahun' => $dt->year, 'label' => $dt->locale('id')->isoFormat('MMMM YYYY')];
        }

        return view('kpi.index', compact('kpiList', 'bintangBulanLalu', 'usulanSp', 'stats', 'bulan', 'tahun', 'bulanOptions'));
    }

    // ============================================================
    // DETAIL KPI SATU KARYAWAN
    // ============================================================
    public function detail(Request $request, $userId = null)
    {
        $user = $userId ? User::findOrFail($userId) : Auth::user();

        // Cek akses: karyawan hanya lihat milik sendiri
        if (Auth::user()->level > 1 && Auth::id() !== $user->id) {
            abort(403);
        }

        // Histori 6 bulan terakhir
        $histori = KpiKinerja::where('user_id', $user->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')
            ->limit(6)
            ->get();

        // KPI bulan ini
        $kpiBulanIni = KpiKinerja::where('user_id', $user->id)
            ->where('bulan', now()->month)
            ->where('tahun', now()->year)
            ->first();

        // SP aktif
        $spAktif = SpKaryawan::where('user_id', $user->id)
            ->where('status', 'aktif')
            ->first();

        // Riwayat SP
        $riwayatSp = SpKaryawan::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        // Rapor terakhir
        $raporTerakhir = RaporKaryawan::where('user_id', $user->id)
            ->where('status', 'selesai')
            ->orderByDesc('tahun')
            ->first();

        // Bintang bulan lalu (untuk banner)
        $bulanLalu = now()->month - 1 ?: 12;
        $tahunLalu = now()->month - 1 ? now()->year : now()->year - 1;
        $isBintangBulanLalu = KpiKinerja::where('user_id', $user->id)
            ->where('bulan', $bulanLalu)
            ->where('tahun', $tahunLalu)
            ->where('is_bintang_jabatan', 1)
            ->exists();

        $namaBulanLalu = Carbon::create($tahunLalu, $bulanLalu, 1)->locale('id')->isoFormat('MMMM YYYY');

        return view('kpi.detail', compact(
            'user', 'histori', 'kpiBulanIni', 'spAktif',
            'riwayatSp', 'raporTerakhir', 'isBintangBulanLalu', 'namaBulanLalu'
        ));
    }

    // ============================================================
    // KONFIRMASI SP — Owner approve/tolak usulan SP
    // ============================================================
    public function konfirmasiSp(Request $request, $spId)
    {
        $sp = SpKaryawan::findOrFail($spId);

        $aksi = $request->aksi; // 'setujui' atau 'tolak'

        if ($aksi === 'setujui') {
            $sp->update([
                'status'            => 'aktif',
                'tanggal_aktif'     => now()->toDateString(),
                'dikonfirmasi_oleh' => Auth::id(),
                'catatan_owner'     => $request->catatan,
            ]);

            // Notif WA ke karyawan
            $fonnte = new FonnteService();
            $user = $sp->user;
            if ($user && $user->no_hp) {
                $msg = "⚠️ *Surat Peringatan — " . strtoupper($sp->level_sp) . "*\n\n";
                $msg .= "Hai {$user->name},\n";
                $msg .= "Kamu menerima " . strtoupper($sp->level_sp) . " per tanggal " . now()->format('d/m/Y') . ".\n\n";
                $msg .= "Alasan: {$sp->alasan}\n\n";
                $msg .= "Pemulihan: Pertahankan poin kinerja ≥60 selama 3 bulan berturut untuk SP turun level.\n\n";
                $msg .= "Hubungi owner jika ada pertanyaan.\n";
                $msg .= "— Pusat Kanopi BSD";
                $fonnte->kirim($user->no_hp, $msg);
            }

            return back()->with('success', 'SP berhasil dikonfirmasi dan notifikasi WA terkirim.');
        } else {
            $sp->update([
                'status'            => 'dicabut',
                'dikonfirmasi_oleh' => Auth::id(),
                'catatan_owner'     => $request->catatan ?? 'Ditolak owner',
            ]);
            return back()->with('success', 'Usulan SP ditolak.');
        }
    }

    // ============================================================
    // INPUT SP MANUAL oleh Owner
    // ============================================================
    public function buatSpManual(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'level_sp' => 'required|in:sp1,sp2,sp3',
            'alasan'   => 'required|string',
        ]);

        SpKaryawan::create([
            'user_id'           => $request->user_id,
            'level_sp'          => $request->level_sp,
            'alasan'            => $request->alasan,
            'trigger_otomatis'  => 0,
            'status'            => 'aktif',
            'tanggal_sp'        => now()->toDateString(),
            'tanggal_aktif'     => now()->toDateString(),
            'dikonfirmasi_oleh' => Auth::id(),
        ]);

        return back()->with('success', 'SP berhasil dibuat.');
    }

    // ============================================================
    // INPUT KOMPLAIN MANUAL
    // ============================================================
    public function simpanKomplain(Request $request)
    {
        $request->validate([
            'user_id'         => 'required|exists:users,id',
            'tanggal'         => 'required|date',
            'sumber'          => 'required|in:customer,internal,supervisor',
            'keterangan'      => 'required|string',
            'bobot_potongan'  => 'required|numeric|min:1|max:20',
        ]);

        DB::table('komplain_karyawan')->insert([
            'user_id'        => $request->user_id,
            'tanggal'        => $request->tanggal,
            'sumber'         => $request->sumber,
            'keterangan'     => $request->keterangan,
            'bobot_potongan' => $request->bobot_potongan,
            'dicatat_oleh'   => Auth::id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return back()->with('success', 'Komplain berhasil dicatat.');
    }

    // ============================================================
    // HITUNG KPI MANUAL (tombol di dashboard owner)
    // ============================================================
    public function hitungManual(Request $request)
    {
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        $service = new KpiService();
        $hasil = $service->hitungKpiBulanan($bulan, $tahun);

        $namaBulan = Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY');
        return back()->with('success', "KPI {$namaBulan} berhasil dihitung. Sukses: {$hasil['sukses']} karyawan.");
    }

    // ============================================================
    // UJIAN ONLINE — Halaman utama karyawan
    // ============================================================
    public function ujianIndex()
    {
        $user = Auth::user();
        $periode = now()->month <= 6 ? 'januari' : 'juli';
        $tahun = now()->year;

        $sesi = UjianSesi::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->first();

        // Cek apakah periode ujian sedang dibuka owner
        $ujianDibuka = DB::table('kpi_setting')
            ->where('key_setting', 'ujian_dibuka_' . $periode . '_' . $tahun)
            ->value('value_setting');

        $rapor = RaporKaryawan::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->first();

        return view('kpi.ujian-index', compact('sesi', 'ujianDibuka', 'rapor', 'periode', 'tahun'));
    }

    // ============================================================
    // MULAI UJIAN
    // ============================================================
    public function ujianMulai()
    {
        $user = Auth::user();
        $periode = now()->month <= 6 ? 'januari' : 'juli';
        $tahun = now()->year;

        // Cek sudah pernah ujian
        $sesiAda = UjianSesi::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->whereIn('status', ['selesai', 'berlangsung'])
            ->first();

        if ($sesiAda) {
            if ($sesiAda->status === 'berlangsung' && !$sesiAda->isExpired()) {
                return redirect()->route('kpi.ujian.kerjakan');
            }
            return redirect()->route('kpi.ujian.index')->with('info', 'Kamu sudah mengerjakan ujian periode ini.');
        }

        // Ambil 20 soal acak sesuai level jabatan
        $soal = UjianSoal::where('jabatan_level', $user->level)
            ->where('is_aktif', 1)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        if ($soal->count() < 20) {
            return back()->with('error', 'Soal ujian belum cukup. Hubungi owner.');
        }

        // Buat sesi ujian
        $sesi = UjianSesi::create([
            'user_id'      => $user->id,
            'periode'      => $periode,
            'tahun'        => $tahun,
            'mulai_pada'   => now(),
            'batas_waktu'  => now()->addMinutes(30),
            'status'       => 'berlangsung',
            'jumlah_soal'  => 20,
        ]);

        // Simpan soal ke sesi
        foreach ($soal as $index => $s) {
            UjianJawaban::create([
                'sesi_id' => $sesi->id,
                'soal_id' => $s->id,
                'urutan'  => $index + 1,
            ]);
        }

        return redirect()->route('kpi.ujian.kerjakan');
    }

    // ============================================================
    // HALAMAN KERJAKAN UJIAN
    // ============================================================
    public function ujianKerjakan()
    {
        $user = Auth::user();
        $periode = now()->month <= 6 ? 'januari' : 'juli';
        $tahun = now()->year;

        $sesi = UjianSesi::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->where('status', 'berlangsung')
            ->firstOrFail();

        // Cek expired
        if ($sesi->isExpired()) {
            $this->prosesSelesaiUjian($sesi);
            return redirect()->route('kpi.ujian.hasil');
        }

        $jawaban = UjianJawaban::where('sesi_id', $sesi->id)
            ->with('soal')
            ->orderBy('urutan')
            ->get();

        $sisaDetik = $sesi->sisaWaktuDetik();

        return view('kpi.ujian-kerjakan', compact('sesi', 'jawaban', 'sisaDetik'));
    }

    // ============================================================
    // SIMPAN JAWABAN (AJAX — per soal)
    // ============================================================
    public function ujianSimpanJawaban(Request $request)
    {
        $sesi = UjianSesi::where('user_id', Auth::id())
            ->where('status', 'berlangsung')
            ->firstOrFail();

        if ($sesi->isExpired()) {
            $this->prosesSelesaiUjian($sesi);
            return response()->json(['expired' => true]);
        }

        UjianJawaban::where('sesi_id', $sesi->id)
            ->where('soal_id', $request->soal_id)
            ->update(['jawaban_karyawan' => $request->jawaban]);

        return response()->json(['ok' => true]);
    }

    // ============================================================
    // SUBMIT UJIAN
    // ============================================================
    public function ujianSubmit()
    {
        $user = Auth::user();
        $periode = now()->month <= 6 ? 'januari' : 'juli';
        $tahun = now()->year;

        $sesi = UjianSesi::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->where('status', 'berlangsung')
            ->firstOrFail();

        $this->prosesSelesaiUjian($sesi);

        return redirect()->route('kpi.ujian.hasil');
    }

    // ============================================================
    // PROSES SELESAI — Nilai otomatis
    // ============================================================
    private function prosesSelesaiUjian(UjianSesi $sesi): void
    {
        $jawaban = UjianJawaban::where('sesi_id', $sesi->id)->with('soal')->get();

        $benar = 0;
        foreach ($jawaban as $j) {
            $isBenar = $j->jawaban_karyawan === $j->soal->jawaban_benar ? 1 : 0;
            $j->update(['is_benar' => $isBenar]);
            if ($isBenar) $benar++;
        }

        $nilai = round(($benar / $sesi->jumlah_soal) * 100, 2);

        $sesi->update([
            'selesai_pada'  => now(),
            'status'        => 'selesai',
            'jumlah_benar'  => $benar,
            'nilai'         => $nilai,
        ]);

        // Update rapor jika sudah ada
        $user = $sesi->user;
        $service = new KpiService();
        $service->hitungRapor($user, $sesi->periode, $sesi->tahun);

        // Notif WA hasil ujian
        if ($user && $user->no_hp) {
            $fonnte = new FonnteService();
            $periode = $sesi->periode === 'januari' ? 'Januari' : 'Juli';
            $msg = "📝 *Hasil Ujian {$periode} {$sesi->tahun}*\n\n";
            $msg .= "Hai {$user->name}!\n";
            $msg .= "Ujian kamu sudah selesai dinilai.\n\n";
            $msg .= "✅ Jawaban benar: {$benar}/{$sesi->jumlah_soal}\n";
            $msg .= "📊 Nilai: {$nilai}/100\n\n";
            $msg .= "Lihat detail rapor di: app.kanopibsd.co.id/kpi/ujian/hasil\n";
            $msg .= "— Pusat Kanopi BSD";
            $fonnte->kirim($user->no_hp, $msg);
        }
    }

    // ============================================================
    // HASIL UJIAN
    // ============================================================
    public function ujianHasil()
    {
        $user = Auth::user();
        $periode = now()->month <= 6 ? 'januari' : 'juli';
        $tahun = now()->year;

        $sesi = UjianSesi::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->where('status', 'selesai')
            ->with(['jawaban.soal'])
            ->first();

        $rapor = RaporKaryawan::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->first();

        return view('kpi.ujian-hasil', compact('sesi', 'rapor', 'periode', 'tahun'));
    }

    // ============================================================
    // BANK SOAL — Owner kelola soal
    // ============================================================
    public function soalIndex()
    {
        $level = request('level', 5);
        $soal = UjianSoal::where('jabatan_level', $level)
            ->orderByDesc('created_at')
            ->paginate(20);

        $levels = [2 => 'Admin', 3 => 'Supervisor', 4 => 'Marketing', 5 => 'Teknisi', 6 => 'Driver'];

        return view('kpi.soal-index', compact('soal', 'levels', 'level'));
    }

    public function soalCreate()
    {
        $levels = [2 => 'Admin', 3 => 'Supervisor', 4 => 'Marketing', 5 => 'Teknisi', 6 => 'Driver'];
        return view('kpi.soal-create', compact('levels'));
    }

    public function soalStore(Request $request)
    {
        $request->validate([
            'jabatan_level' => 'required|in:2,3,4,5,6',
            'pertanyaan'    => 'required|string',
            'pilihan_a'     => 'required|string',
            'pilihan_b'     => 'required|string',
            'pilihan_c'     => 'required|string',
            'pilihan_d'     => 'required|string',
            'jawaban_benar' => 'required|in:a,b,c,d',
        ]);

        UjianSoal::create([
            ...$request->only(['jabatan_level', 'pertanyaan', 'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d', 'jawaban_benar']),
            'dibuat_oleh' => Auth::id(),
        ]);

        return redirect()->route('kpi.soal.index', ['level' => $request->jabatan_level])
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function soalEdit($id)
    {
        $soal = UjianSoal::findOrFail($id);
        $levels = [2 => 'Admin', 3 => 'Supervisor', 4 => 'Marketing', 5 => 'Teknisi', 6 => 'Driver'];
        return view('kpi.soal-edit', compact('soal', 'levels'));
    }

    public function soalUpdate(Request $request, $id)
    {
        $soal = UjianSoal::findOrFail($id);
        $soal->update($request->only([
            'jabatan_level', 'pertanyaan', 'pilihan_a', 'pilihan_b',
            'pilihan_c', 'pilihan_d', 'jawaban_benar', 'is_aktif'
        ]));
        return redirect()->route('kpi.soal.index', ['level' => $soal->jabatan_level])
            ->with('success', 'Soal berhasil diupdate.');
    }

    public function soalHapus($id)
    {
        UjianSoal::findOrFail($id)->delete();
        return back()->with('success', 'Soal berhasil dihapus.');
    }

    // ============================================================
    // BUKA / TUTUP PERIODE UJIAN (Owner)
    // ============================================================
    public function toggleUjian(Request $request)
    {
        $periode = $request->periode;
        $tahun   = $request->tahun ?? now()->year;
        $aksi    = $request->aksi; // 'buka' atau 'tutup'
        $key     = 'ujian_dibuka_' . $periode . '_' . $tahun;

        DB::table('kpi_setting')->updateOrInsert(
            ['key_setting' => $key],
            ['value_setting' => $aksi === 'buka' ? '1' : '0', 'keterangan' => "Ujian {$periode} {$tahun}"]
        );

        $status = $aksi === 'buka' ? 'dibuka' : 'ditutup';
        return back()->with('success', "Ujian periode " . ucfirst($periode) . " {$tahun} {$status}.");
    }

    // ============================================================
    // RAPOR — Owner lihat semua rapor
    // ============================================================
    public function raporIndex()
    {
        $periode = request('periode', now()->month <= 6 ? 'januari' : 'juli');
        $tahun   = request('tahun', now()->year);

        $rapor = RaporKaryawan::where('periode', $periode)
            ->where('tahun', $tahun)
            ->with('user')
            ->orderByDesc('nilai_total')
            ->get();

        $periodeOptions = [];
        for ($t = now()->year; $t >= now()->year - 1; $t--) {
            $periodeOptions[] = ['periode' => 'januari', 'tahun' => $t, 'label' => "Januari {$t}"];
            $periodeOptions[] = ['periode' => 'juli', 'tahun' => $t, 'label' => "Juli {$t}"];
        }

        return view('kpi.rapor-index', compact('rapor', 'periode', 'tahun', 'periodeOptions'));
    }
}