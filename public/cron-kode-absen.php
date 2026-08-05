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
use App\Services\TelegramService;
use Carbon\Carbon;

$log = [];
$tanggal = today();

// ═══════════════════════════════════════════════════════
// GENERATE + KIRIM KODE PER KARYAWAN
// ═══════════════════════════════════════════════════════

$karyawan = User::where('level', '!=', 1) // bukan owner
                ->where('status', 'aktif')
                ->get();

$terkirim = 0;
$gagal    = 0;
$sudahAda = 0;
$skip     = 0;

foreach ($karyawan as $k) {
    try {
        $existing = KodeAbsen::whereDate('tanggal', $tanggal)->where('user_id', $k->id)->first();

        if ($existing) {
            $kode = $existing->kode;
            $sudahAda++;
        } else {
            $kode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            KodeAbsen::create([
                'kode'    => $kode,
                'tanggal' => $tanggal,
                'user_id' => $k->id,
            ]);
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
