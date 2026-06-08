<?php
// FILE: public_html/app/public/cron-kode-absen.php
// Dijalankan via cron job jam 06:30 WIB (23:30 UTC hari sebelumnya)
// Otomatis generate kode harian + kirim WA ke semua karyawan

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
use Carbon\Carbon;

$log = [];
$tanggal = today();

// ═══════════════════════════════════════════════════════
// GENERATE KODE HARIAN
// ═══════════════════════════════════════════════════════

// Cek apakah kode hari ini sudah ada
$kodeHariIni = KodeAbsen::whereDate('tanggal', $tanggal)->first();

if (!$kodeHariIni) {
    // Generate kode 6 karakter alphanumeric
    $kode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
    $kodeHariIni = KodeAbsen::create([
        'kode'    => $kode,
        'tanggal' => $tanggal,
    ]);
    $log[] = "Kode hari ini: {$kode}";
} else {
    $kode = $kodeHariIni->kode;
    $log[] = "Kode sudah ada: {$kode}";
}

// ═══════════════════════════════════════════════════════
// KIRIM WA KE SEMUA KARYAWAN WAJIB ABSEN
// ═══════════════════════════════════════════════════════

$karyawan = User::where('level', '!=', 1) // bukan owner
                ->where('status', 'aktif')
                ->whereNotNull('no_hp')
                ->get();

$terkirim = 0;
$gagal    = 0;

foreach ($karyawan as $k) {
    $pesan = "🏠 *PUSAT KANOPI BSD*\n"
           . "━━━━━━━━━━━━━━━━━━\n"
           . "📅 " . $tanggal->translatedFormat('l, d F Y') . "\n\n"
           . "🔑 *KODE ABSEN HARI INI:*\n"
           . "┌─────────────┐\n"
           . "│   *{$kode}*   │\n"
           . "└─────────────┘\n\n"
           . "⏰ Absen masuk mulai jam *06:30*\n"
           . "📍 Pastikan kamu berada di lokasi kerja\n\n"
           . "Kode berlaku untuk hari ini saja.\n"
           . "_CanopiBSD System_";

    $result = kirimWA($k->no_hp, $pesan);

    if ($result) {
        $terkirim++;
        $log[] = "✓ Terkirim ke: {$k->name} ({$k->no_hp})";
    } else {
        $gagal++;
        $log[] = "✗ Gagal ke: {$k->name} ({$k->no_hp})";
    }

    // Delay 1 detik antar kirim (hindari rate limit Fonnte)
    sleep(1);
}

// ═══════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════

echo '<pre style="background:#1a1a2e;color:lime;padding:20px;font-family:monospace;">';
echo "=== KODE ABSEN HARIAN ===\n";
echo "Waktu  : " . now()->format('d/m/Y H:i:s') . "\n";
echo "Kode   : {$kode}\n";
echo "Tanggal: " . $tanggal->format('d/m/Y') . "\n\n";
echo "--- HASIL PENGIRIMAN ---\n";
foreach ($log as $l) echo $l . "\n";
echo "\n✅ Terkirim : {$terkirim}\n";
echo "❌ Gagal    : {$gagal}\n";
echo "\n=== SELESAI ===";
echo '</pre>';

// ═══════════════════════════════════════════════════════
// HELPER
// ═══════════════════════════════════════════════════════

function kirimWA(string $noHp, string $pesan): bool
{
    $token = env('FONNTE_TOKEN', '');
    if (!$token) return false;

    $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($result, true);
    return $data['status'] ?? false;
}