<?php
// FILE: app/Services/GajiService.php

namespace App\Services;

use App\Models\User;
use App\Models\Absensi;
use App\Models\SlipGaji;
use App\Models\Kasbon;
use App\Models\TabunganKaryawan;
use App\Services\SettingGajiService;
use App\Services\LiburService;
use App\Services\KerjaHariLiburService;
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

    /**
     * Pembagi jam kerja untuk menghitung tarif lembur per jam.
     * Dikunci Bos: lembur = (gaji_harian / 9) x 1,2 x jam_lembur.
     */
    const LEMBUR_PEMBAGI = 9;
    const LEMBUR_PENGALI = 1.2;

    /**
     * Nominal bonus lembur — SATU-SATUNYA tempat rumus ini ditulis.
     *
     * Dulu rumusnya ada di dua tempat dengan angka BERBEDA: AbsensiController
     * memakai /7,5 sementara GajiService memakai /9, jadi nominal di layar absensi
     * tidak pernah cocok dengan nominal di slip.
     *
     * Lebih parah: controller menambahkan nominalnya ke `absensi.gaji_hari_ini` DAN
     * menyimpan `absensi.lembur_jam`, lalu slip membayar lagi dari `lembur_jam` itu —
     * untuk pegawai harian (gaji pokoknya diakumulasi dari `gaji_hari_ini`) lemburnya
     * benar-benar terbayar dua kali. Sekarang yang membayar hanya slip, sekali.
     *
     * Murni — bisa diuji tanpa database (tests/kerja-hari-libur/test_lembur.php).
     */
    public static function bonusLembur($gajiHarian, $jamLembur): float
    {
        $gaji = max(0.0, (float) ($gajiHarian ?? 0));
        $jam  = max(0.0, (float) ($jamLembur ?? 0));

        return ($gaji / self::LEMBUR_PEMBAGI) * self::LEMBUR_PENGALI * $jam;
    }

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
            // Slip yang BELUM dibayar boleh dihitung ulang -- perlu saat absensi
            // dikoreksi atau kebijakan berubah setelah slip terlanjur dibuat
            // (kasus nyata 31 Ags: bonus KPI & tabungan wajib ditunda, slip draft
            // sudah terlanjur ada). Slip "dibayar" TIDAK PERNAH: prosesBayar sudah
            // memajukan cicilan kasbon & menambah saldo tabungan.
            if (!SettingGajiService::bolehHitungUlang($existing->status)) {
                throw new \Exception("Slip uang makan {$bulan}/{$tahun} sudah DIBAYAR — tidak bisa dihitung ulang.");
            }
            $existing->delete();
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
            // Slip yang BELUM dibayar boleh dihitung ulang -- perlu saat absensi
            // dikoreksi atau kebijakan berubah setelah slip terlanjur dibuat
            // (kasus nyata 31 Ags: bonus KPI & tabungan wajib ditunda, slip draft
            // sudah terlanjur ada). Slip "dibayar" TIDAK PERNAH: prosesBayar sudah
            // memajukan cicilan kasbon & menambah saldo tabungan.
            if (!SettingGajiService::bolehHitungUlang($existing->status)) {
                throw new \Exception("Slip gaji {$bulan}/{$tahun} sudah DIBAYAR — tidak bisa dihitung ulang.");
            }
            $existing->delete();
        }

        $user = User::with('tunjangan')->findOrFail($userId);

        // ── Rekap absensi SEMUA bulan ──────────────────────
        $absensi = Absensi::where('user_id', $userId)
                          ->whereMonth('tanggal', $bulan)
                          ->whereYear('tanggal', $tahun)
                          ->get();

        // Hari yang DIAKTIFKAN masuk kerja ikut dihitung sebagai hari kerja biasa —
        // keputusan Bos: aktivasi membatalkan libur tanpa pengganti. Tanggal itu
        // masuk penyebut (hitungHariKerja di bawah, lewat override aktivasi) DAN
        // pembilang di sini, jadi persentasenya konsisten tanpa membuang record.
        //
        // `hari_kerja_libur`/`upah_hari_libur` tetap dipisah untuk upah EKSTRA:
        // hanya baris yang benar-benar bekerja (hadir/telat/setengah hari) yang
        // dibayar — diaktifkan lalu mangkir tidak dapat apa-apa.
        $svcLibur     = app(KerjaHariLiburService::class);
        $statistik    = $svcLibur->statistikKehadiran($absensi);
        $absensiLibur = $svcLibur->hanyaKerjaLiburBekerja($absensi);

        $hariHadir      = $statistik['hadir'];
        $hariAlpha      = $statistik['alpha'];
        $hariTelat      = $statistik['telat'];
        $hariIzin       = $statistik['izin'];
        $hariKerjaLibur = $absensiLibur->count();
        $upahHariLibur  = (float) $absensiLibur->sum('upah_hari_libur');

        // Hari kerja bulan ini (dikurangi jadwal libur karyawan ini)
        $hariKerja = app(LiburService::class)->hitungHariKerja($user, $bulan, $tahun);
        $persenHadir = $svcLibur->persenHadir($hariHadir, $hariKerja);

        // ── Potongan telat ─────────────────────────────────
        // Dihitung duluan karena gaji pokok harian butuh angkanya (lihat di bawah).
        $potonganTelat  = $absensi->sum('potongan_telat');

        // ── Gaji pokok ─────────────────────────────────────
        $gajiPokok = 0;
        if ($user->tipe_gaji === 'bulanan') {
            $gajiPokok = $user->gaji_bulanan ?? 0;
        } else {
            // Harian — akumulasi dari absensi. gaji_hari_ini sudah dikurangi potongan
            // di AbsensiController, sementara potongan yang SAMA dikurangi lagi lewat
            // totalPotongan di bawah -> kepotong dua kali. Dikembalikan ke KOTOR dulu
            // biar potongan kepotong tepat sekali dan slip tetap transparan
            // (gaji pokok kotor + baris potongan sendiri).
            $gajiPokok = $svcLibur->gajiPokokKotor(
                (float) $absensi->sum('gaji_hari_ini'),
                (float) $potonganTelat
            );
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
        // Kelas KPI TETAP dihitung & disimpan (rapor kehadiran tetap jalan), tapi
        // NOMINALNYA hanya dibayar kalau saklar bonus KPI nyala. Ditunda per
        // keputusan Elvan 31 Ags 2026 — dinyalakan lagi lewat halaman Pengaturan Gaji,
        // tanpa ubah kode. Default MATI (lihat SettingGajiService::aktifDari).
        $setGaji   = SettingGajiService::ambil();
        $kelasKpi  = $this->hitungKelasKpi($hariTelat, $hariAlpha, $persenHadir);
        $bonusKpi  = SettingGajiService::nilaiBonusKpi(
            self::BONUS_KPI[$kelasKpi],
            SettingGajiService::aktifDari($setGaji, 'bonus_kpi_aktif')
        );

        // ── Lembur ─────────────────────────────────────────
        // SATU-SATUNYA tempat lembur dibayar. `absensi.gaji_hari_ini` sengaja
        // TIDAK lagi mengandung nominal lembur (lihat AbsensiController::absenPulang),
        // jadi tidak ada pembayaran kedua lewat akumulasi gaji harian.
        $totalLembur    = $absensi->sum('lembur_jam');
        $bonusLembur    = self::bonusLembur($user->gaji_harian, $totalLembur);

        // ── Kasbon ─────────────────────────────────────────
        $potonganKasbon = $this->hitungCicilanKasbon($userId);

        // ── Potongan insidental ────────────────────────────
        $potonganInsidental = $this->hitungPotonganInsidental($userId);

        // ── Tabungan ───────────────────────────────────────
        // Barisnya TETAP ada di slip (Rp 0 kalau saklar mati) — transparan, bukan
        // disembunyikan: karyawan belum diberi tahu soal potongan ini (Elvan 31 Ags).
        $tabunganWajib   = SettingGajiService::nilaiTabunganWajib(
            self::TABUNGAN_WAJIB,
            SettingGajiService::aktifDari($setGaji, 'tabungan_wajib_aktif')
        );
        $tabungan        = TabunganKaryawan::firstOrCreate(['user_id' => $userId]);
        $tabunganLebaran = $tabungan->tabungan_lebaran_per_bulan ?? 0;

        // ── Total ──────────────────────────────────────────
        // Pegawai HARIAN: upah hari libur sudah masuk gaji_hari_ini (jangan ditambah lagi = 2x bayar).
        // Pegawai BULANAN: gaji pokoknya tetap, jadi upah hari libur ditambah 1x.
        // Uang makan masuk sekali lewat $umSiang, tidak ditambah dari snapshot.
        $totalPendapatan = $svcLibur->totalPendapatan(
            $user->tipe_gaji, $gajiPokok, $umSiang, $totalTunjangan, $bonusKpi, $bonusLembur, $upahHariLibur
        );
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
            'hari_kerja_libur'      => $hariKerjaLibur,
            'gaji_pokok'            => $gajiPokok,
            'upah_hari_libur'       => $upahHariLibur,
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