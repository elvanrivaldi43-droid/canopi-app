<?php
/**
 * cron-kpi.php
 * Simpan di: /home/u8221523/public_html/app/public/cron-kpi.php
 * Daftarkan di cron-job.org:
 *   URL: https://app.kanopibsd.co.id/cron-kpi.php?key=canopi_cron_2026
 *   Jadwal: Setiap tanggal 1 jam 00:05 WIB (Asia/Bangkok)
 */

$key = $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

// Bootstrap Laravel
require_once __DIR__ . '/../bootstrap/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

use App\Services\KpiService;
use App\Services\FonnteService;
use App\Models\KpiKinerja;
use App\Models\User;
use Carbon\Carbon;

header('Content-Type: text/plain; charset=utf-8');

// Hitung untuk bulan kemarin (cron jalan tgl 1, hitung bulan lalu)
$bulanLalu = Carbon::now()->subMonth();
$bulan = (int) $bulanLalu->format('m');
$tahun = (int) $bulanLalu->format('Y');

echo "=== HITUNG KPI BULANAN ===\n";
echo "Periode: {$bulan}/{$tahun}\n";
echo date('Y-m-d H:i:s') . "\n\n";

try {
    $service = new KpiService();

    // 1. Hitung KPI semua karyawan
    $hasil = $service->hitungKpiBulanan($bulan, $tahun);
    echo "Sukses: {$hasil['sukses']} karyawan\n";
    echo "Gagal: {$hasil['gagal']} karyawan\n\n";
    foreach ($hasil['log'] as $log) {
        echo $log . "\n";
    }

    // 2. Cek pemulihan SP
    echo "\n=== CEK PEMULIHAN SP ===\n";
    $service->cekPemulihanSp($bulan, $tahun);
    echo "Selesai\n";

    // 3. Kirim notif WA ke semua karyawan
    echo "\n=== KIRIM NOTIF WA ===\n";
    $namaBulan = Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY');
    $fonnte = new FonnteService();

    $kpiList = KpiKinerja::where('bulan', $bulan)
        ->where('tahun', $tahun)
        ->with('user')
        ->get();

    foreach ($kpiList as $kpi) {
        $user = $kpi->user;
        if (!$user || !$user->no_hp) continue;

        $bintang = str_repeat('⭐', $kpi->bintang);
        $bonus   = $kpi->bonus_nominal > 0
            ? "Bonus: Rp " . number_format($kpi->bonus_nominal, 0, ',', '.')
            : "Tidak ada bonus bulan ini";

        $msg = "📊 *Rekap Poin Kinerja {$namaBulan}*\n\n";
        $msg .= "Hai {$user->name}!\n";
        $msg .= "Berikut hasil poin kinerjamu:\n\n";
        $msg .= "⭐ Poin: *{$kpi->total_poin}* {$bintang}\n";
        $msg .= "💰 {$bonus}\n";

        if ($kpi->is_bintang_jabatan) {
            $msg .= "\n🏆 *SELAMAT! Kamu Bintang {$namaBulan}!*\n";
            $msg .= "Penghargaan ini akan tampil di dasbor bulan depan.\n";
        } elseif ($kpi->bintang === 1) {
            $msg .= "\n💪 Yuk tingkatkan performa bulan depan!\n";
            $msg .= "Kamu bisa lebih baik dari ini.\n";
        }

        $msg .= "\nCek detail di: app.kanopibsd.co.id\n";
        $msg .= "— Pusat Kanopi BSD";

        $fonnte->kirim($user->no_hp, $msg);
        echo "WA terkirim ke {$user->name}\n";
    }

    // 4. Notif ke owner: ringkasan + bintang + usulan SP
    $owner = User::where('level', 1)->first();
    if ($owner && $owner->no_hp) {
        $totalKaryawan = $kpiList->count();
        $bintang5 = $kpiList->where('bintang', 5)->where('is_alpha', 0)->count();
        $redZone  = $kpiList->where('total_poin', '<', 45)->count();
        $bintangJabatan = $kpiList->where('is_bintang_jabatan', 1)->pluck('user.name')->join(', ');

        $msgOwner = "📊 *Rekap KPI {$namaBulan}*\n\n";
        $msgOwner .= "Total karyawan: {$totalKaryawan}\n";
        $msgOwner .= "⭐⭐⭐⭐⭐ Poin tertinggi: {$bintang5} org\n";
        $msgOwner .= "🔴 Red Zone: {$redZone} org\n";
        if ($bintangJabatan) {
            $msgOwner .= "\n🏆 Bintang bulan ini:\n{$bintangJabatan}\n";
        }
        $msgOwner .= "\nCek detail di: app.kanopibsd.co.id/kpi\n";
        $msgOwner .= "— Sistem CanopiBSD";

        $fonnte->kirim($owner->no_hp, $msgOwner);
        echo "WA ringkasan terkirim ke Owner\n";
    }

    echo "\n=== SELESAI " . date('H:i:s') . " ===\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}