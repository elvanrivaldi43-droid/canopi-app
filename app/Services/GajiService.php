<?php
// FILE: app/Services/GajiService.php

namespace App\Services;

use App\Models\User;
use App\Models\Absensi;
use App\Models\SlipGaji;
use App\Models\Kasbon;
use App\Models\TabunganKaryawan;
use Carbon\Carbon;

class GajiService
{
    const BATAS_AMAN       = 500000;
    const TABUNGAN_WAJIB   = 100000;
    const POTONGAN_PER_JAM = 20000;

    const BONUS_KPI = [
        'platinum' => 300000,
        'gold'     => 150000,
        'silver'   => 75000,
        'none'     => 0,
    ];

    // Kriteria KPI
    const KPI_PLATINUM_MAX_TELAT  = 0;
    const KPI_PLATINUM_MAX_ALPHA  = 0;
    const KPI_PLATINUM_MIN_HADIR  = 100; // %
    const KPI_GOLD_MAX_TELAT      = 1;
    const KPI_GOLD_MAX_ALPHA      = 0;
    const KPI_GOLD_MIN_HADIR      = 90;  // %
    const KPI_SILVER_MAX_TELAT    = 2;
    const KPI_SILVER_MAX_ALPHA    = 0;
    const KPI_SILVER_MIN_HADIR    = 80;  // %

    // ═══════════════════════════════════════════════════════
    // GENERATE SLIP UANG MAKAN (periode 1-15)
    // ═══════════════════════════════════════════════════════
    public function generateUangMakan(int $userId, int $bulan, int $tahun): SlipGaji
    {
        // Cek duplikat
        $existing = SlipGaji::where('user_id', $userId)
                            ->where('periode', 'uang_makan')
                            ->where('bulan', $bulan)
                            ->where('tahun', $tahun)
                            ->first();

        if ($existing) {
            throw new \Exception("Slip uang makan {$bulan}/{$tahun} sudah pernah digenerate.");
        }

        $user    = User::findOrFail($userId);
        $absensi = $this->getAbsensi($userId, $bulan, $tahun, 1, 15);

        // Hitung uang makan 1-15
        $totalUM = $absensi->sum('uang_makan_hari_ini');
        // Hanya ambil UM dari tanggal 1-15
        $totalUM = Absensi::where('user_id', $userId)
                          ->whereMonth('tanggal', $bulan)
                          ->whereYear('tanggal', $tahun)
                          ->whereDay('tanggal', '<=', 15)
                          ->sum('uang_makan_hari_ini');

        $slip = SlipGaji::create([
            'user_id'          => $userId,
            'periode'          => 'uang_makan',
            'bulan'            => $bulan,
            'tahun'            => $tahun,
            'tanggal_generate' => today(),
            'status'           => 'draft',
            'total_uang_makan' => $totalUM,
            'total_pendapatan' => $totalUM,
            'total_potongan'   => 0,
            'gaji_bersih'      => $totalUM,
        ]);

        return $slip;
    }

    // ═══════════════════════════════════════════════════════
    // GENERATE SLIP GAJI BULANAN (periode 16-akhir)
    // ═══════════════════════════════════════════════════════
    public function generateGajiBulanan(int $userId, int $bulan, int $tahun): SlipGaji
    {
        // Cek duplikat
        $existing = SlipGaji::where('user_id', $userId)
                            ->where('periode', 'gaji_bulanan')
                            ->where('bulan', $bulan)
                            ->where('tahun', $tahun)
                            ->first();

        if ($existing) {
            throw new \Exception("Slip gaji {$bulan}/{$tahun} sudah pernah digenerate.");
        }

        $user = User::with('tunjangan')->findOrFail($userId);

        // ── Rekap absensi SEMUA bulan ──────────────────────
        $absensi = Absensi::where('user_id', $userId)
                          ->whereMonth('tanggal', $bulan)
                          ->whereYear('tanggal', $tahun)
                          ->get();

        $hariHadir = $absensi->whereIn('status', ['hadir','telat','setengah_hari'])->count();
        $hariAlpha = $absensi->where('status', 'alpha')->count();
        $hariTelat = $absensi->where('status', 'telat')->count();
        $hariIzin  = $absensi->whereIn('status', ['sakit','izin','cuti','dinas_luar'])->count();

        // Hari kerja bulan ini (Senin-Sabtu)
        $hariKerja = $this->hitungHariKerja($bulan, $tahun);
        $persenHadir = $hariKerja > 0 ? ($hariHadir / $hariKerja) * 100 : 0;

        // ── Gaji pokok ─────────────────────────────────────
        $gajiPokok = 0;
        if ($user->tipe_gaji === 'bulanan') {
            $gajiPokok = $user->gaji_bulanan ?? 0;
        } else {
            // Harian — akumulasi dari absensi
            $gajiPokok = $absensi->sum('gaji_hari_ini');
        }

        // ── Uang makan 16-akhir bulan ──────────────────────
        $hariAkhir = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        $umSiang   = Absensi::where('user_id', $userId)
                            ->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun)
                            ->whereDay('tanggal', '>=', 16)
                            ->sum('uang_makan_hari_ini');

        // ── Tunjangan ──────────────────────────────────────
        $totalTunjangan = $user->tunjangan->sum('pivot.nominal');

        // ── KPI ────────────────────────────────────────────
        $kelasKpi  = $this->hitungKelasKpi($hariTelat, $hariAlpha, $persenHadir);
        $bonusKpi  = self::BONUS_KPI[$kelasKpi];

        // ── Lembur ─────────────────────────────────────────
        $totalLembur    = $absensi->sum('lembur_jam');
        $gajiPerJam     = ($user->gaji_harian ?? 0) / 9;
        $bonusLembur    = $totalLembur * $gajiPerJam * 1.2;

        // ── Potongan telat ─────────────────────────────────
        $potonganTelat  = $absensi->sum('potongan_telat');

        // ── Kasbon ─────────────────────────────────────────
        $potonganKasbon = $this->hitungCicilanKasbon($userId);

        // ── Potongan insidental ────────────────────────────
        $potonganInsidental = $this->hitungPotonganInsidental($userId);

        // ── Tabungan ───────────────────────────────────────
        $tabunganWajib   = self::TABUNGAN_WAJIB;
        $tabungan        = TabunganKaryawan::firstOrCreate(['user_id' => $userId]);
        $tabunganLebaran = $tabungan->tabungan_lebaran_per_bulan ?? 0;

        // ── Total ──────────────────────────────────────────
        $totalPendapatan = $gajiPokok + $umSiang + $totalTunjangan + $bonusKpi + $bonusLembur;
        $totalPotongan   = $potonganTelat + $potonganKasbon + $potonganInsidental + $tabunganWajib + $tabunganLebaran;
        $gajiBersih      = $totalPendapatan - $totalPotongan;

        // ── Warning batas aman ─────────────────────────────
        $warningBatasAman = $gajiBersih < self::BATAS_AMAN;
        $status = $warningBatasAman ? 'menunggu_konfirmasi' : 'draft';

        $slip = SlipGaji::create([
            'user_id'               => $userId,
            'periode'               => 'gaji_bulanan',
            'bulan'                 => $bulan,
            'tahun'                 => $tahun,
            'tanggal_generate'      => today(),
            'status'                => $status,
            'hari_hadir'            => $hariHadir,
            'hari_alpha'            => $hariAlpha,
            'hari_telat'            => $hariTelat,
            'hari_izin'             => $hariIzin,
            'gaji_pokok'            => $gajiPokok,
            'total_uang_makan'      => $umSiang,
            'total_tunjangan'       => $totalTunjangan,
            'bonus_kpi'             => $bonusKpi,
            'kelas_kpi'             => $kelasKpi,
            'bonus_lembur'          => $bonusLembur,
            'jam_lembur'            => $totalLembur,
            'potongan_telat'        => $potonganTelat,
            'potongan_kasbon'       => $potonganKasbon,
            'potongan_insidental'   => $potonganInsidental,
            'tabungan_wajib'        => $tabunganWajib,
            'tabungan_lebaran'      => $tabunganLebaran,
            'total_pendapatan'      => $totalPendapatan,
            'total_potongan'        => $totalPotongan,
            'gaji_bersih'           => $gajiBersih,
            'warning_batas_aman'    => $warningBatasAman,
        ]);

        return $slip;
    }

    // ═══════════════════════════════════════════════════════
    // PROSES BAYAR GAJI
    // ═══════════════════════════════════════════════════════
    public function prosesBayar(SlipGaji $slip, int $ownerId): void
    {
        if ($slip->status === 'dibayar') {
            throw new \Exception("Slip ini sudah dibayar sebelumnya.");
        }

        if ($slip->warning_batas_aman && !$slip->owner_konfirmasi) {
            throw new \Exception("Slip ini perlu konfirmasi owner karena gaji bersih di bawah batas aman.");
        }

        // Update kasbon
        if ($slip->potongan_kasbon > 0 && $slip->periode === 'gaji_bulanan') {
            $this->prosesPotonganKasbon($slip->user_id, $slip->potongan_kasbon);
        }

        // Update potongan insidental
        if ($slip->potongan_insidental > 0 && $slip->periode === 'gaji_bulanan') {
            $this->prosesPotonganInsidental($slip->user_id);
        }

        // Update tabungan
        if ($slip->periode === 'gaji_bulanan') {
            $tabungan = TabunganKaryawan::firstOrCreate(['user_id' => $slip->user_id]);
            $tabungan->increment('tabungan_wajib_total', $slip->tabungan_wajib);
            if ($slip->tabungan_lebaran > 0) {
                $tabungan->increment('tabungan_lebaran_total', $slip->tabungan_lebaran);
            }
        }

        $slip->update([
            'status'        => 'dibayar',
            'tanggal_bayar' => today(),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // HITUNG KELAS KPI
    // ═══════════════════════════════════════════════════════
    public function hitungKelasKpi(int $telat, int $alpha, float $persenHadir): string
    {
        if ($alpha > 0) return 'none';

        if ($telat <= self::KPI_PLATINUM_MAX_TELAT && $persenHadir >= self::KPI_PLATINUM_MIN_HADIR) {
            return 'platinum';
        }
        if ($telat <= self::KPI_GOLD_MAX_TELAT && $persenHadir >= self::KPI_GOLD_MIN_HADIR) {
            return 'gold';
        }
        if ($telat <= self::KPI_SILVER_MAX_TELAT && $persenHadir >= self::KPI_SILVER_MIN_HADIR) {
            return 'silver';
        }
        return 'none';
    }

    // ═══════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════

    private function getAbsensi(int $userId, int $bulan, int $tahun, int $dari, int $sampai)
    {
        return Absensi::where('user_id', $userId)
                      ->whereMonth('tanggal', $bulan)
                      ->whereYear('tanggal', $tahun)
                      ->whereDay('tanggal', '>=', $dari)
                      ->whereDay('tanggal', '<=', $sampai)
                      ->get();
    }

    private function hitungHariKerja(int $bulan, int $tahun): int
    {
        $hariKerja = 0;
        $hariAkhir = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        for ($i = 1; $i <= $hariAkhir; $i++) {
            $tgl = Carbon::createFromDate($tahun, $bulan, $i);
            if ($tgl->dayOfWeek !== Carbon::SUNDAY) $hariKerja++;
        }
        return $hariKerja;
    }

    private function hitungCicilanKasbon(int $userId): float
    {
        return \App\Models\Kasbon::where('user_id', $userId)
                                 ->where('status', 'aktif')
                                 ->where(function($q) {
                                     $q->whereNull('ditunda_sampai')
                                       ->orWhere('ditunda_sampai', '<', today());
                                 })
                                 ->sum('cicilan_per_bulan');
    }

    private function hitungPotonganInsidental(int $userId): float
    {
        return \App\Models\PotonganInsidental::where('user_id', $userId)
                                             ->where('status', 'aktif')
                                             ->sum('cicilan_per_bulan');
    }

    private function prosesPotonganKasbon(int $userId, float $nominal): void
    {
        $kasbons = \App\Models\Kasbon::where('user_id', $userId)
                                     ->where('status', 'aktif')
                                     ->where(function($q) {
                                         $q->whereNull('ditunda_sampai')
                                           ->orWhere('ditunda_sampai', '<', today());
                                     })
                                     ->get();

        foreach ($kasbons as $kasbon) {
            $kasbon->cicilan_ke++;
            $kasbon->sisa_kasbon -= $kasbon->cicilan_per_bulan;
            if ($kasbon->sisa_kasbon <= 0) {
                $kasbon->sisa_kasbon = 0;
                $kasbon->status = 'lunas';
            }
            $kasbon->save();
        }
    }

    private function prosesPotonganInsidental(int $userId): void
    {
        $potongans = \App\Models\PotonganInsidental::where('user_id', $userId)
                                                   ->where('status', 'aktif')
                                                   ->get();
        foreach ($potongans as $p) {
            $p->cicilan_ke++;
            $p->sisa -= $p->cicilan_per_bulan;
            if ($p->sisa <= 0) {
                $p->sisa = 0;
                $p->status = 'lunas';
            }
            $p->save();
        }
    }
}