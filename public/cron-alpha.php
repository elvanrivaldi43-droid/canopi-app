<?php
// FILE: public_html/app/public/cron-alpha.php
// Dijalankan via cron job cPanel 2x sehari:
// Jam 13:00 WIB → auto alpha tidak masuk (setelah batas setengah hari)
// Jam 20:00 WIB → auto alpha tidak pulang

// Security key
$key = $argv[1] ?? $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

// Load Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Absensi;
use App\Models\IzinAbsen;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;

$jam     = now()->format('H:i');
$tanggal = today();
$log     = [];

// ═══════════════════════════════════════════════════════
// JAM 13:00 — Auto Alpha untuk yang TIDAK MASUK sama sekali
// Catatan: yang sudah masuk tapi belum absen siang → BUKAN alpha
// mereka kena potongan skip siang Rp 20.000, bukan alpha
// ═══════════════════════════════════════════════════════
if ($jam >= '13:00' && $jam <= '13:15') {

    $karyawan = User::where('level', '!=', 1)
                    ->where('status', 'aktif')
                    ->get();

    foreach ($karyawan as $k) {
        // Cek apakah sudah absen MASUK (jam_masuk tidak null)
        $sudahMasuk = Absensi::where('user_id', $k->id)
                             ->whereDate('tanggal', $tanggal)
                             ->whereNotNull('jam_masuk') // ← hanya cek yang benar-benar belum masuk
                             ->exists();

        // Cek apakah sudah ada izin approved
        $adaIzin = IzinAbsen::where('user_id', $k->id)
                            ->whereDate('tanggal', $tanggal)
                            ->where('status', 'approved')
                            ->exists();

        // Cek apakah sudah di-alpha sebelumnya
        $sudahAlpha = Absensi::where('user_id', $k->id)
                             ->whereDate('tanggal', $tanggal)
                             ->where('status', 'alpha')
                             ->exists();

        // Cek apakah hari ini jadwal libur karyawan itu
        $sedangLibur = app(LiburService::class)->isLibur($k, $tanggal);

        // Hanya alpha jika: belum masuk sama sekali + tidak ada izin + belum alpha + bukan hari libur
        if (!$sudahMasuk && !$adaIzin && !$sudahAlpha && !$sedangLibur) {
            Absensi::create([
                'user_id'             => $k->id,
                'tanggal'             => $tanggal,
                'status'              => 'alpha',
                'gaji_hari_ini'       => 0,
                'uang_makan_hari_ini' => 0,
                'potongan_telat'      => 0,
            ]);

            app(TelegramService::class)->kirim($k->telegram_chat_id,
                "⚠️ *INFO ABSENSI*\n" .
                "Hai {$k->name}, kamu tercatat ALPHA hari ini ({$tanggal->format('d/m/Y')}) karena tidak absen masuk.\n" .
                "Jika ada kesalahan, hubungi mandor untuk koreksi."
            );

            $mandorOwner = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();
            foreach ($mandorOwner as $m) {
                app(TelegramService::class)->kirim($m->telegram_chat_id,
                    "❌ *ALPHA*\n" .
                    "{$k->name} ({$k->jabatan}) tidak masuk tanpa keterangan hari ini ({$tanggal->format('d/m/Y')})."
                );
            }

            $log[] = "ALPHA tidak masuk: {$k->name}";
        }
    }
}

// ═══════════════════════════════════════════════════════
// JAM 20:00 — Auto Alpha untuk yang tidak absen pulang
// Hanya yang sudah absen masuk tapi belum pulang
// ═══════════════════════════════════════════════════════
if ($jam >= '20:00' && $jam <= '20:15') {

    $belumPulang = Absensi::whereDate('tanggal', $tanggal)
                          ->whereNotNull('jam_masuk')   // sudah masuk
                          ->whereNull('jam_pulang')      // belum pulang
                          ->whereNotIn('status', ['alpha','sakit','izin','cuti','dinas_luar'])
                          ->with('user')
                          ->get();

    foreach ($belumPulang as $absen) {
        $absen->update([
            'status'              => 'alpha',
            'keterangan'          => 'Tidak absen pulang — otomatis alpha jam 20:00',
            'gaji_hari_ini'       => 0,
            'uang_makan_hari_ini' => 0,
        ]);

        $k = $absen->user;

        app(TelegramService::class)->kirim($k->telegram_chat_id,
            "⚠️ *INFO ABSENSI*\n" .
            "Hai {$k->name}, kamu tercatat ALPHA karena tidak absen pulang hari ini ({$tanggal->format('d/m/Y')}).\n" .
            "Hubungi mandor jika ada kesalahan."
        );

        $mandorOwner = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();
        foreach ($mandorOwner as $m) {
            app(TelegramService::class)->kirim($m->telegram_chat_id,
                "❌ *TIDAK ABSEN PULANG*\n" .
                "{$k->name} ({$k->jabatan}) tidak absen pulang hari ini — otomatis ALPHA."
            );
        }

        $log[] = "ALPHA tidak pulang: {$k->name}";
    }
}

// ═══════════════════════════════════════════════════════
// Output hasil
// ═══════════════════════════════════════════════════════
echo '<pre style="background:#1a1a2e;color:lime;padding:20px;">';
echo "=== AUTO ALPHA CRON ===\n";
echo "Waktu : " . now()->format('d/m/Y H:i:s') . "\n";
echo "Window: jam 13:00-13:15 (alpha tidak masuk) | jam 20:00-20:15 (alpha tidak pulang)\n\n";
if (empty($log)) {
    echo "Tidak ada yang diproses (di luar window waktu atau semua sudah absen).\n";
} else {
    foreach ($log as $l) echo "✓ {$l}\n";
}
echo "\n=== SELESAI ===";
echo '</pre>';
