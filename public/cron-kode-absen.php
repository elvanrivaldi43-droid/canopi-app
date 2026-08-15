<?php
// FILE: public_html/app/public/cron-kode-absen.php
// Dijalankan via cron job jam 06:30 WIB (23:30 UTC hari sebelumnya)
// Otomatis generate kode harian PER KARYAWAN + kirim ke Telegram masing-masing

$key = $argv[1] ?? $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\KodeAbsen;
use App\Models\Absensi;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;

$log = [];
$tanggal = today();

// ═══════════════════════════════════════════════════════
// GENERATE + KIRIM KODE PER KARYAWAN
// ═══════════════════════════════════════════════════════

// Karyawan yang izin/sakit/cuti/dinas luar hari ini gak usah dikasih kode absen
$offHariIni = Absensi::whereDate('tanggal', $tanggal)
                     ->whereIn('status', ['sakit', 'izin', 'cuti', 'dinas_luar'])
                     ->pluck('user_id');

$karyawan = User::where('level', '!=', 1) // bukan owner
                ->where('status', 'aktif')
                ->whereNotIn('id', $offHariIni)
                ->get();

$terkirim = 0;
$gagal    = 0;
$sudahAda = 0;
$skip     = 0;

foreach ($karyawan as $k) {
    try {
        if (app(LiburService::class)->isLibur($k, $tanggal)) {
            $log[] = "⏭ Skip (jadwal libur): {$k->name}";
            $skip++;
            continue;
        }

        // Satu jalur tulis dengan halaman "Kode Absen Hari Ini" dan tombol "Aktifkan
        // Masuk Hari Libur": createOrFirst di dalam model, tabrakan ditangkap unique
        // (tanggal, user_id) di DB. Dua eksekusi barengan tidak lagi bisa menghasilkan
        // dua kode valid untuk satu karyawan.
        $baris = KodeAbsen::barisHariIniUntuk($k);
        $kode  = $baris->kode;

        // Kodenya sudah ada sebelum eksekusi ini -> berarti sudah pernah dikirim/
        // ditampilkan hari ini. Jangan kirim ulang (6 Agustus: karyawan menerima
        // kode 4x dalam satu pagi karena retry cron + trigger manual).
        if (!$baris->wasRecentlyCreated) {
            $sudahAda++;
            $log[] = "⏭ Skip (kode sudah dikirim hari ini): {$k->name}";
            continue;
        }

        if (!$k->telegram_chat_id) {
            $log[] = "⏭ Skip (belum connect Telegram): {$k->name} — kode: {$kode}";
            $skip++;
            continue;
        }

        $pesan = "🏠 *PUSAT KANOPI BSD*\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "📅 " . $tanggal->translatedFormat('l, d F Y') . "\n\n"
               . "🔑 *KODE ABSEN KAMU HARI INI:*\n"
               . "┌─────────────┐\n"
               . "│   *{$kode}*   │\n"
               . "└─────────────┘\n\n"
               . "⏰ Absen masuk mulai jam *06:30*\n"
               . "📍 Pastikan kamu berada di lokasi kerja\n\n"
               . "Kode ini cuma buat kamu, jangan dibagikan ke orang lain.\n"
               . "Berlaku untuk hari ini saja.\n"
               . "_CanopiBSD System_";

        $result = app(TelegramService::class)->kirim($k->telegram_chat_id, $pesan);

        if ($result) {
            $terkirim++;
            $log[] = "✓ Terkirim ke: {$k->name}";
        } else {
            $gagal++;
            $log[] = "✗ Gagal ke: {$k->name}";
        }
    } catch (\Throwable $e) {
        $gagal++;
        $log[] = "✗ ERROR untuk {$k->name}: " . $e->getMessage();
    }
}

// ═══════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════

echo '<pre style="background:#1a1a2e;color:lime;padding:20px;font-family:monospace;">';
echo "=== KODE ABSEN HARIAN (PER KARYAWAN) ===\n";
echo "Waktu  : " . now()->format('d/m/Y H:i:s') . "\n";
echo "Tanggal: " . $tanggal->format('d/m/Y') . "\n\n";
echo "--- HASIL ---\n";
foreach ($log as $l) echo $l . "\n";
echo "\n✅ Terkirim   : {$terkirim}\n";
echo "❌ Gagal      : {$gagal}\n";
echo "♻️  Sudah ada  : {$sudahAda}\n";
echo "⏭  Belum connect: {$skip}\n";
echo "\n=== SELESAI ===";
echo '</pre>';
