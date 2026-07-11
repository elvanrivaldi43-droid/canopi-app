<?php

namespace App\Services;

use App\Models\User;
use App\Models\KpiKinerja;
use App\Models\SpKaryawan;
use App\Models\RaporKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiService
{
    // ============================================================
    // HITUNG KPI BULANAN — dipanggil oleh cron tiap akhir bulan
    // ============================================================
    public function hitungKpiBulanan(int $bulan, int $tahun): array
    {
        $hasil = ['sukses' => 0, 'gagal' => 0, 'log' => []];

        // Ambil semua karyawan aktif level 2-6
        $karyawan = User::where('status', 'aktif')
            ->whereIn('level', [2, 3, 4, 5, 6])
            ->get();

        foreach ($karyawan as $user) {
            try {
                $this->hitungKpiSatuKaryawan($user, $bulan, $tahun);
                $hasil['sukses']++;
                $hasil['log'][] = "✅ {$user->name} (level {$user->level})";
            } catch (\Exception $e) {
                $hasil['gagal']++;
                $hasil['log'][] = "❌ {$user->name}: " . $e->getMessage();
                Log::error("KPI error user {$user->id}: " . $e->getMessage());
            }
        }

        // Setelah semua dihitung, tentukan bintang per jabatan
        $this->tentukanBintangJabatan($bulan, $tahun);

        // Cek & usulkan SP jika ada yang Red Zone
        $this->cekUsulanSp($bulan, $tahun);

        return $hasil;
    }

    // ============================================================
    // HITUNG KPI SATU KARYAWAN
    // ============================================================
    public function hitungKpiSatuKaryawan(User $user, int $bulan, int $tahun): KpiKinerja
    {
        $poinKehadiran = 0;
        $poinTugas     = 0;
        $poinLeads     = 0;
        $poinBbm       = 0;
        $poinKomplain  = 20; // default penuh
        $isAlpha       = false;

        $detailKehadiran = [];
        $detailTugas     = [];
        $detailBbm       = [];

        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        // ---- KOMPONEN 1: KEHADIRAN ----
        $bobotKehadiran = $this->bobotKehadiran($user->level);
        $absensi = DB::table('absensi')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->get();

        $hariKerja  = $absensi->count();
        $jumlahHadir = $absensi->whereIn('status', ['hadir', 'telat', 'setengah'])->count();
        $jumlahAlpha = $absensi->where('status', 'alpha')->count();
        $jumlahTelat = $absensi->where('status', 'telat')->count();
        $jumlahIzin  = $absensi->whereIn('status', ['izin', 'sakit', 'cuti'])->count();

        if ($jumlahAlpha > 0) {
            $isAlpha = true;
        }

        // Hitung poin kehadiran
        if ($hariKerja > 0) {
            $poinKehadiran = $bobotKehadiran;
            $poinKehadiran -= ($jumlahAlpha * 8);   // -8 per alpha
            $poinKehadiran -= ($jumlahTelat * 3);   // -3 per telat
            $poinKehadiran = max(0, $poinKehadiran);
        }

        $detailKehadiran = [
            'hari_kerja'  => $hariKerja,
            'hadir'       => $jumlahHadir,
            'alpha'       => $jumlahAlpha,
            'telat'       => $jumlahTelat,
            'izin'        => $jumlahIzin,
            'poin'        => $poinKehadiran,
        ];

        // ---- KOMPONEN 2: TUGAS HARIAN ----
        $bobotTugas = $this->bobotTugas($user->level);
        $tugas = DB::table('tugas_assignee')
            ->join('tugas_harian', 'tugas_assignee.tugas_id', '=', 'tugas_harian.id')
            ->where('tugas_assignee.user_id', $user->id)
            ->whereBetween('tugas_harian.tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->whereNotNull('tugas_harian.tanggal')
            ->select('tugas_assignee.status')
            ->get();

        $totalTugas   = $tugas->count();
        $tugasSelesai = $tugas->where('status', 'selesai')->count();
        $tugasTidak   = $tugas->where('status', 'tidak_selesai')->count();

        if ($totalTugas > 0) {
            $persenSelesai = $tugasSelesai / $totalTugas;
            $poinTugas = round($persenSelesai * $bobotTugas, 2);
        } else {
            // Tidak ada tugas = poin penuh (tidak dihukum karena tidak ditugaskan)
            $poinTugas = $bobotTugas;
        }

        $detailTugas = [
            'total'   => $totalTugas,
            'selesai' => $tugasSelesai,
            'tidak'   => $tugasTidak,
            'persen'  => $totalTugas > 0 ? round(($tugasSelesai / $totalTugas) * 100) : 100,
            'poin'    => $poinTugas,
        ];

        // ---- KOMPONEN 3: LEADS (Admin & Marketing) ----
        if (in_array($user->level, [2, 4])) {
            $bobotLeads = $this->bobotLeads($user->level);
            $totalLeads = DB::table('pipeline_leads')
                ->where('created_by', $user->id)
                ->whereBetween('created_at', [$awalBulan, $akhirBulan])
                ->count();

            $leadsUpdate = DB::table('pipeline_leads')
                ->where('created_by', $user->id)
                ->whereBetween('updated_at', [$awalBulan, $akhirBulan])
                ->where('status', '!=', 'lead')
                ->count();

            // Target leads per bulan (bisa dikonfigurasi, default 5)
            $targetLeads = 5;
            $skorLeads = min(1, $totalLeads / $targetLeads);
            $poinLeads = round($skorLeads * $bobotLeads, 2);
        }

        // ---- KOMPONEN 4: BBM (Driver saja) ----
        if ($user->level === 6) {
            $bobotBbm = 30;
            $logBbm = DB::table('log_bensin')
                ->where('user_id', $user->id)
                ->whereNotNull('km_akhir')
                ->whereBetween('created_at', [$awalBulan, $akhirBulan])
                ->get();

            if ($logBbm->count() > 0) {
                $standarKmPerLiter = 9; // dari memori proyek
                $totalKonsumsi = $logBbm->avg('konsumsi_kmpl');
                $rasio = min(1, $totalKonsumsi / $standarKmPerLiter);
                $poinBbm = round($rasio * $bobotBbm, 2);

                $detailBbm = [
                    'rata_konsumsi' => round($totalKonsumsi, 2),
                    'standar'       => $standarKmPerLiter,
                    'jumlah_log'    => $logBbm->count(),
                    'poin'          => $poinBbm,
                ];
            } else {
                $poinBbm = $bobotBbm; // tidak ada log = tidak dihukum
            }
        }

        // ---- KOMPONEN 5: KOMPLAIN ----
        $komplain = DB::table('komplain_karyawan')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->sum('bobot_potongan');

        $poinKomplain = max(0, 20 - $komplain);

        // ---- TOTAL POIN (dinormalisasi ke 100) ----
        // Setiap jabatan punya total bobot berbeda, harus dinormalisasi
        $totalBobot = $this->bobotKehadiran($user->level)
                    + $this->bobotTugas($user->level)
                    + $this->bobotLeads($user->level)
                    + ($user->level === 6 ? 30 : 0) // BBM khusus driver
                    + 20; // komplain selalu 20

        $totalMentah = $poinKehadiran + $poinTugas + $poinLeads + $poinBbm + $poinKomplain;
        $totalPoin = $totalBobot > 0 ? round(($totalMentah / $totalBobot) * 100, 2) : 0;
        $totalPoin = min(100, max(0, $totalPoin));

        $bintang = KpiKinerja::hitungBintang($totalPoin);
        $bonus   = KpiKinerja::hitungBonus($bintang, $isAlpha);

        // ---- SIMPAN / UPDATE ----
        $kpi = KpiKinerja::updateOrCreate(
            ['user_id' => $user->id, 'bulan' => $bulan, 'tahun' => $tahun],
            [
                'poin_kehadiran'   => $poinKehadiran,
                'poin_tugas'       => $poinTugas,
                'poin_leads'       => $poinLeads,
                'poin_bbm'         => $poinBbm,
                'poin_komplain'    => $poinKomplain,
                'total_poin'       => $totalPoin,
                'bintang'          => $bintang,
                'is_alpha'         => $isAlpha ? 1 : 0,
                'bonus_nominal'    => $bonus,
                'detail_kehadiran' => json_encode($detailKehadiran),
                'detail_tugas'     => json_encode($detailTugas),
                'detail_bbm'       => json_encode($detailBbm),
                'dihitung_pada'    => now(),
            ]
        );

        return $kpi;
    }

    // ============================================================
    // TENTUKAN BINTANG JABATAN
    // Jalankan setelah semua KPI dihitung
    // ============================================================
    public function tentukanBintangJabatan(int $bulan, int $tahun): void
    {
        // Reset dulu semua bintang jabatan bulan ini
        KpiKinerja::where('bulan', $bulan)->where('tahun', $tahun)
            ->update(['is_bintang_jabatan' => 0]);

        // Syarat: poin >= 75, alpha = 0
        $syaratMinPoin = 75;

        $levels = [2, 3, 4, 5, 6];
        foreach ($levels as $level) {
            // Ambil user IDs dengan level ini
            $userIds = User::where('level', $level)->where('status', 'aktif')->pluck('id');

            if ($userIds->isEmpty()) continue;

            // Cari yang tertinggi & memenuhi syarat
            $terbaik = KpiKinerja::where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->whereIn('user_id', $userIds)
                ->where('total_poin', '>=', $syaratMinPoin)
                ->where('is_alpha', 0)
                ->orderByDesc('total_poin')
                ->first();

            if ($terbaik) {
                $terbaik->update(['is_bintang_jabatan' => 1]);
            }
        }
    }

    // ============================================================
    // CEK USULAN SP OTOMATIS
    // ============================================================
    public function cekUsulanSp(int $bulan, int $tahun): void
    {
        // Cari karyawan Red Zone (poin < 45)
        $redZone = KpiKinerja::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where('total_poin', '<', 45)
            ->with('user')
            ->get();

        foreach ($redZone as $kpi) {
            $user = $kpi->user;
            if (!$user) continue;

            // Hitung berapa bulan berturut Red Zone
            $bulanCek = $bulan - 1;
            $tahunCek = $tahun;
            if ($bulanCek < 1) { $bulanCek = 12; $tahunCek--; }

            $bulanRedZoneSebelumnya = KpiKinerja::where('user_id', $user->id)
                ->where('bulan', $bulanCek)
                ->where('tahun', $tahunCek)
                ->where('total_poin', '<', 45)
                ->exists();

            if ($bulanRedZoneSebelumnya) {
                // 2 bulan berturut Red Zone → usul SP1
                $spAktif = SpKaryawan::where('user_id', $user->id)
                    ->whereIn('status', ['usulan', 'aktif'])
                    ->exists();

                if (!$spAktif) {
                    SpKaryawan::create([
                        'user_id'           => $user->id,
                        'level_sp'          => 'sp1',
                        'alasan'            => "KPI Red Zone 2 bulan berturut ({$bulanCek}/{$tahunCek} dan {$bulan}/{$tahun}). Total poin: {$kpi->total_poin}",
                        'trigger_otomatis'  => 1,
                        'status'            => 'usulan',
                        'tanggal_sp'        => now()->toDateString(),
                    ]);
                }
            }
        }
    }

    // ============================================================
    // BOBOT POIN PER JABATAN
    // ============================================================
    private function bobotKehadiran(int $level): int
    {
        return match($level) {
            5, 6 => 40, // Teknisi & Driver: kehadiran penting
            2, 3 => 25, // Admin & Supervisor
            4    => 20, // Marketing
            default => 30,
        };
    }

    private function bobotTugas(int $level): int
    {
        return match($level) {
            5, 6 => 40, // Teknisi & Driver
            2    => 35, // Admin
            3    => 40, // Supervisor
            4    => 10, // Marketing: tugas kecil
            default => 30,
        };
    }

    private function bobotLeads(int $level): int
    {
        return match($level) {
            2 => 25, // Admin: leads 25 poin
            4 => 50, // Marketing: leads 50 poin
            default => 0,
        };
    }

    // ============================================================
    // HITUNG RAPOR 6 BULANAN
    // ============================================================
    public function hitungRapor(User $user, string $periode, int $tahun): RaporKaryawan
    {
        // Tentukan 6 bulan yang dihitung
        $bulanList = $periode === 'januari'
            ? [7, 8, 9, 10, 11, 12] // Juli-Des tahun sebelumnya
            : [1, 2, 3, 4, 5, 6];   // Jan-Jun tahun ini

        $tahunKpi = $periode === 'januari' ? $tahun - 1 : $tahun;

        // Ambil rata-rata KPI 6 bulan
        $avgKpi = KpiKinerja::where('user_id', $user->id)
            ->whereIn('bulan', $bulanList)
            ->where('tahun', $tahunKpi)
            ->avg('total_poin') ?? 0;

        // Nilai KPI untuk rapor (bobot 50%)
        $nilaiKpi = round($avgKpi * 0.5, 2);

        // Ambil nilai ujian (bobot 30%)
        $ujianSesi = \App\Models\UjianSesi::where('user_id', $user->id)
            ->where('periode', $periode)
            ->where('tahun', $tahun)
            ->where('status', 'selesai')
            ->first();

        $nilaiUjian = $ujianSesi ? round($ujianSesi->nilai * 0.3, 2) : 0;

        // Nilai SP (bobot 20%)
        $spAktif = SpKaryawan::where('user_id', $user->id)
            ->where('status', 'aktif')
            ->first();

        $nilaiSp = match($spAktif?->level_sp) {
            'sp1' => 10,  // SP1 = 10 dari 20
            'sp2' => 5,   // SP2 = 5 dari 20
            'sp3' => 0,   // SP3 = 0
            default => 20, // bersih = 20
        };

        $nilaiTotal = $nilaiKpi + $nilaiUjian + $nilaiSp;

        // Kelas sebelumnya
        $raporSebelumnya = RaporKaryawan::where('user_id', $user->id)
            ->where('status', 'selesai')
            ->orderByDesc('tahun')
            ->orderByRaw("FIELD(periode, 'juli', 'januari')")
            ->first();

        $kelasSebelumnya = $raporSebelumnya?->kelas_baru ?? null;
        $kelasBaru = RaporKaryawan::tentukanKelas($nilaiTotal);

        // Tentukan naik/turun/tetap
        $urutan = ['red_zone' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
        $kelasNaik = 0;
        if ($kelasSebelumnya && isset($urutan[$kelasSebelumnya], $urutan[$kelasBaru])) {
            if ($urutan[$kelasBaru] > $urutan[$kelasSebelumnya]) $kelasNaik = 1;
            elseif ($urutan[$kelasBaru] < $urutan[$kelasSebelumnya]) $kelasNaik = -1;
        }

        // Kenaikan gaji hanya jika kelas naik
        $kenaikanGaji = ($kelasNaik === 1) ? RaporKaryawan::kenaikanGaji($kelasBaru) : 0;

        // Simpan rapor
        $rapor = RaporKaryawan::updateOrCreate(
            ['user_id' => $user->id, 'periode' => $periode, 'tahun' => $tahun],
            [
                'nilai_kpi'        => $nilaiKpi,
                'nilai_ujian'      => $nilaiUjian,
                'nilai_sp'         => $nilaiSp,
                'nilai_total'      => $nilaiTotal,
                'kelas_sebelumnya' => $kelasSebelumnya,
                'kelas_baru'       => $kelasBaru,
                'kenaikan_gaji'    => $kenaikanGaji,
                'kelas_naik'       => $kelasNaik,
                'status'           => $ujianSesi ? 'selesai' : 'pending',
                'id_ujian_sesi'    => $ujianSesi?->id,
            ]
        );

        // Update gaji jika naik kelas
        if ($kenaikanGaji > 0) {
            $user->increment('gaji_bulanan', $kenaikanGaji);
        }

        return $rapor;
    }

    // ============================================================
    // CEK PEMULIHAN SP OTOMATIS (jalankan tiap akhir bulan)
    // ============================================================
    public function cekPemulihanSp(int $bulan, int $tahun): void
    {
        $spAktif = SpKaryawan::where('status', 'aktif')->get();

        foreach ($spAktif as $sp) {
            $kpi = KpiKinerja::where('user_id', $sp->user_id)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->first();

            if (!$kpi) continue;

            // Syarat bulan bersih: poin >= 60, alpha = 0
            $bulanBersih = $kpi->total_poin >= 60 && $kpi->is_alpha == 0;

            if ($bulanBersih) {
                $sp->increment('bulan_bersih_berturut');

                // 3 bulan bersih berturut → SP turun 1 level
                if ($sp->bulan_bersih_berturut >= 3) {
                    if ($sp->level_sp === 'sp1') {
                        $sp->update(['status' => 'pulih', 'tanggal_pulih' => now()->toDateString()]);
                    } elseif ($sp->level_sp === 'sp2') {
                        $sp->update(['level_sp' => 'sp1', 'bulan_bersih_berturut' => 0]);
                    } elseif ($sp->level_sp === 'sp3') {
                        $sp->update(['level_sp' => 'sp2', 'bulan_bersih_berturut' => 0]);
                    }
                }
            } else {
                // Reset timer jika tidak bersih
                $sp->update([
                    'bulan_bersih_berturut' => 0,
                    'reset_timer_pada' => now()->toDateString(),
                ]);
            }
        }
    }
}