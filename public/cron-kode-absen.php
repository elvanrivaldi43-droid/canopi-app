<?php
// FILE: public_html/app/public/cron-kode-absen.php
// Dijalankan via cron job jam 06:30 WIB (23:30 UTC hari sebelumnya)
// Otomatis generate kode harian PER KARYAWAN + kirim ke Telegram masing-masing

// Load Laravel dulu: kunci ada di .env, baru terbaca setelah bootstrap (belum ada query DB di sini).
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Security key — TIDAK boleh ditulis di kode (repo publik). Kunci ada di .env (CRON_KEY),
// dicek lewat App\Services\CronAuth. Gagal tertutup bila CRON_KEY kosong/lemah.
$key = $argv[1] ?? $_GET['key'] ?? '';
if (!\App\Services\CronAuth::valid((string) $key)) {
    http_response_code(403);
    die('Forbidden');
}

// Mode cek (?cek=1): membuktikan kunci benar TANPA menjalankan apa pun — aman untuk uji di production.
if (($_GET['cek'] ?? '') !== '') {
    die('OK');
}

// Sebelum 06:00 WIB konfirmasi Penugasan masih terbuka: baris tidak_hadir yang ditulis
// sekarang akan bertahan walau pekerja lalu berbalik Hadir. Keluar tanpa efek samping.
if (now()->format('H:i') < '06:00') {
    header('Content-Type: text/plain');
    die('Terlalu pagi');
}

use App\Models\User;
use App\Models\KodeAbsen;
use App\Models\Absensi;
use App\Services\TelegramService;
use App\Services\LiburService;
use App\Services\PenugasanAturan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$log = [];
$tanggal = today();

// ═══════════════════════════════════════════════════════
// GENERATE + KIRIM KODE PER KARYAWAN
// ═══════════════════════════════════════════════════════

// Karyawan yang izin/sakit/cuti/dinas luar hari ini gak usah dikasih kode absen
$offHariIni = Absensi::whereDate('tanggal', $tanggal)
                     ->whereIn('status', ['sakit', 'izin', 'cuti', 'dinas_luar', 'tidak_hadir'])
                     ->pluck('user_id');

// ── Penugasan Harian: kode mengikuti konfirmasi malam ──
// Hanya berlaku bila rencana BENAR-BENAR disimpan (disimpan_at terisi; baris kunci
// pengingat bernilai NULL bukan rencana). Tanpa rencana -> semua dapat kode seperti biasa.
$tglStr     = $tanggal->toDateString();
$jawabanKonf  = collect(); // user_id => jawaban (null = belum menjawab)
$adaPenugasan = collect(); // user_id => true
$tanpaJawaban = [];
// Gagal baca data penugasan -> gagal TERBUKA: kode dikirim normal ke semua (seperti sebelum fitur ini),
// jangan sampai satu query rusak membuat seluruh karyawan tak dapat kode.
try {
    $adaRencana = DB::table('rencana_harian')->where('tanggal', $tglStr)->whereNotNull('disimpan_at')->exists();
    if ($adaRencana) {
        $jawabanKonf  = DB::table('konfirmasi_hadir')->where('tanggal', $tglStr)->pluck('jawaban', 'user_id');
        $adaPenugasan = DB::table('penugasan_harian')->where('tanggal', $tglStr)->distinct()->pluck('user_id')->flip();
    }
} catch (\Throwable $e) {
    $adaRencana   = false;
    $jawabanKonf  = collect();
    $adaPenugasan = collect();
    $log[] = "✗ ERROR baca rencana (kode dikirim normal ke semua): " . $e->getMessage();
}

if ($adaRencana) {

    // Jawab 'Tidak Hadir' = libur tanpa gaji, tanpa kode. firstOrCreate: baris yang sudah
    // ada (izin/sakit/dll) TIDAK ditimpa; aman dijalankan ulang.
    foreach ($jawabanKonf as $uid => $jwb) {
        if ($jwb !== 'tidak_hadir') continue;
        try {
            $u = User::find($uid);
            if ($u && !app(LiburService::class)->isLibur($u, $tanggal)) {
                Absensi::firstOrCreate(
                    ['user_id' => $uid, 'tanggal' => $tglStr],
                    ['status' => 'tidak_hadir', 'gaji_hari_ini' => 0, 'uang_makan_hari_ini' => 0, 'potongan_telat' => 0]
                );
            }
            $log[] = "⏭ Skip (jawab tidak hadir): " . ($u->name ?? "user #$uid");
        } catch (\Throwable $e) {
            $log[] = "✗ ERROR catat tidak hadir user #$uid: " . $e->getMessage();
        }
        $offHariIni->push($uid);
    }
}

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

        // Teknisi/Driver/Mandor yang ada di rencana wajib menjawab 'Hadir' untuk dapat kode.
        // Diam TIDAK PERNAH dianggap hadir. Level lain jalan seperti biasa.
        if (PenugasanAturan::ikut((int) $k->level)) {
            $adaDiRencana = PenugasanAturan::adaDiRencana($adaPenugasan->has($k->id), $jawabanKonf->has($k->id));
            $keputusan    = PenugasanAturan::keputusanKode($adaRencana, $adaDiRencana, $jawabanKonf->get($k->id));
            if ($keputusan !== 'kirim') {
                // 'catat_tidak_hadir' sudah disaring lewat $offHariIni; ini cuma jaga-jaga.
                $log[] = $keputusan === 'tanpa_jawaban'
                    ? "⏭ Skip (tak menjawab konfirmasi): {$k->name}"
                    : "⏭ Skip (jawab tidak hadir): {$k->name}";
                // Sudah dibuatkan kode manual oleh Owner -> jangan dilaporkan "tak dapat kode".
                if ($keputusan === 'tanpa_jawaban'
                    && !KodeAbsen::where(['tanggal' => $tglStr, 'user_id' => $k->id])->exists()) {
                    $tanpaJawaban[] = $k->name;
                }
                $skip++;
                continue;
            }
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

// Satu ringkasan ke Mandor Bertugas + Owner: siapa tak dapat kode karena diam.
// Gagal kirim tidak boleh menggagalkan cron.
if ($pesanRingkas = PenugasanAturan::pesanTanpaJawaban($tanpaJawaban)) {
    try {
        $tg       = app(TelegramService::class);
        $mandorId = DB::table('setting_penugasan')->where('id', 1)->value('mandor_bertugas_id');
        $chat     = $mandorId ? User::where('id', $mandorId)->value('telegram_chat_id') : null;
        if ($chat) $tg->kirim($chat, $pesanRingkas);
        $tg->kirimOwner($pesanRingkas);
        $log[] = "ℹ Ringkasan tak menjawab dikirim (" . count($tanpaJawaban) . " orang)";
    } catch (\Throwable $e) {
        $log[] = "✗ ERROR ringkasan tak menjawab: " . $e->getMessage();
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
