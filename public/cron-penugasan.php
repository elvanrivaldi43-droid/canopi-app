<?php
// Cron pengingat Penugasan Harian. Dijalankan beberapa kali per hari (jadwal di crontab VPS).
// Jadwal/urutan "hanya maju" ada di PenugasanAturan; file ini cuma mengeksekusi.
// Anti kirim ganda: nilai pengingat ditulis ke DB DULU (UPDATE bersyarat), baru kirim.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$key = $argv[1] ?? $_GET['key'] ?? '';
if (!\App\Services\CronAuth::valid((string) $key)) {
    http_response_code(403);
    die('Forbidden');
}
// Mode cek: tanpa efek samping.
if (($_GET['cek'] ?? '') !== '') {
    die('OK');
}

use App\Models\User;
use App\Models\LiburNasional;
use App\Services\LiburService;
use App\Services\PenugasanAturan;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;

$log = [];
$now = now();
$jam = $now->format('H:i');
// Tanggal kerja SAMA dengan halaman Rencana & batas jawab: sebelum 06:00 = hari ini, sejak 06:00 = besok.
$tglStr = PenugasanAturan::tanggalKerja($now);
$tgl = \Carbon\Carbon::parse($tglStr);
$kataHari = PenugasanAturan::kataHari($tglStr, $now);
$telegram = new TelegramService();
$link = url('/penugasan/rencana');
$log[] = "Jam $jam, tanggal kerja $tglStr";

// ── 1) Pengingat Mandor (hanya sesudah 12:00) ──
// Gerbang 12:00 sengaja dipertahankan walau pengingatMandor() baru jatuh tempo >= 19:00:
// pengingat Mandor = urusan menyusun rencana MALAM untuk besok. Run pagi (00:00-11:59)
// tak boleh pernah menegur Mandor / mengabari Owner, sekalipun jam di aturan diubah kelak.
// Pengingat PEKERJA tidak digerbang di sini (05:00/05:45 justru pagi).
try {
    $libur = LiburNasional::where('tanggal_mulai', '<=', $tglStr)->where('tanggal_selesai', '>=', $tglStr)->exists();
    if ($now->format('H') < 12) {
        $log[] = 'Mandor: sebelum 12:00, lewati';
    } elseif ($libur) {
        $log[] = 'Mandor: tanggal kerja libur nasional, lewati';
    } else {
        $rencana = DB::table('rencana_harian')->where('tanggal', $tglStr)->first();
        $lama = $rencana->pengingat_mandor ?? null;
        $kode = PenugasanAturan::pengingatMandor($jam, (bool) ($rencana->disimpan_at ?? null), $lama);
        if ($kode === null) {
            $log[] = 'Mandor: tidak ada pengingat jatuh tempo';
        } else {
            // Kunci dulu: baris dibuat bila belum ada, lalu UPDATE bersyarat (hanya satu proses yang menang).
            DB::table('rencana_harian')->insertOrIgnore(['tanggal' => $tglStr, 'created_at' => $now, 'updated_at' => $now]);
            $menang = DB::table('rencana_harian')->where('tanggal', $tglStr)->whereNull('disimpan_at')
                ->whereRaw('pengingat_mandor <=> ?', [$lama])
                ->update(['pengingat_mandor' => $kode, 'updated_at' => $now]);
            if (!$menang) {
                $log[] = "Mandor: $kode sudah ditangani proses lain";
            } elseif ($kode === 'owner') {
                $sudah = DB::table('penugasan_harian')->where('tanggal', $tglStr)->pluck('user_id')->all();
                $halangan = DB::table('izin_absen')->where('status', 'approved')->whereDate('tanggal', $tglStr)->pluck('user_id')->all();
                $belum = [];
                foreach (User::where('status', 'aktif')->whereIn('level', PenugasanAturan::LEVEL_IKUT)->orderBy('name')->get() as $u) {
                    if (in_array($u->id, $sudah) || in_array($u->id, $halangan)) continue;
                    if (app(LiburService::class)->isLibur($u, $tgl)) continue;
                    $belum[] = $u->name;
                }
                $pesan = "Mandor belum mengisi rencana besok (" . $tgl->translatedFormat('d F Y') . ").\n"
                    . "Belum ada tugas: " . ($belum ? implode(', ', $belum) : '-') . "\n$link";
                $telegram->kirimOwner($pesan);
                $log[] = 'Mandor: owner dikabari';
            } else {
                $teks = [
                    '1900' => "Rencana kerja besok belum diisi. Silakan isi di sini: $link",
                    '2030' => "Rencana kerja besok BELUM diisi. Mohon segera: $link",
                    '2100' => "Rencana kerja besok masih belum diisi. Batas 22:00, setelah itu diambil alih Owner: $link",
                ][$kode];
                $mid = DB::table('setting_penugasan')->where('id', 1)->value('mandor_bertugas_id');
                $chat = $mid ? User::where('id', $mid)->value('telegram_chat_id') : null;
                $ok = $chat ? $telegram->kirim($chat, $teks) : false;
                $log[] = "Mandor: $kode " . ($ok ? 'terkirim' : 'GAGAL/tanpa Telegram');
            }
        }
    }
} catch (\Throwable $e) {
    $log[] = 'Mandor ERROR: ' . $e->getMessage();
}

// ── 2) Pengingat pekerja yang belum menjawab ──
$teksPekerja = ['2130' => 'Belum dijawab', '0500' => 'Batas jawab jam 06:00', '0545' => '15 menit lagi, tanpa jawaban tidak dapat kode absen'];
try {
    $rows = DB::table('konfirmasi_hadir')->where('tanggal', $tglStr)->whereNull('jawaban')->get();
    foreach ($rows as $r) {
        try {
            $kode = PenugasanAturan::pengingatPekerja($jam, null, $r->pengingat);
            if ($kode === null) continue;
            $menang = DB::table('konfirmasi_hadir')->where('id', $r->id)->whereNull('jawaban')
                ->whereRaw('pengingat <=> ?', [$r->pengingat])
                ->update(['pengingat' => $kode, 'updated_at' => $now]);
            if (!$menang) continue;

            $u = User::find($r->user_id);
            if (!$u || !$u->telegram_chat_id) {
                $log[] = "Pekerja #{$r->user_id}: $kode dilewati (tanpa Telegram)";
                continue;
            }
            $tugas = DB::table('penugasan_harian as h')
                ->join('project_tahap as t', 't.id', '=', 'h.project_tahap_id')
                ->join('projects as p', 'p.id', '=', 't.project_id')
                ->where('h.tanggal', $tglStr)->where('h.user_id', $r->user_id)
                ->get(['h.porsi', 't.nama_tahap', 't.project_id', 'p.nama_project', 'p.nama_customer']);
            $labels = $tugas->map(fn($x) => ($x->nama_project ?: ($x->nama_customer ?: 'Project #' . $x->project_id))
                . ' - ' . $x->nama_tahap . ' (' . ($x->porsi === 'penuh' ? 'penuh' : 'setengah hari') . ')')->all();
            $pesan = $teksPekerja[$kode] . ".\n" . PenugasanAturan::pesanTanya($kataHari, $tgl->translatedFormat('d F Y'), $labels ?: ['-']);
            $ok = $telegram->kirimTombol($u->telegram_chat_id, $pesan, PenugasanAturan::tombolHadir((int) $r->id));
            $log[] = "Pekerja {$u->name}: $kode " . ($ok ? 'terkirim' : 'GAGAL');
        } catch (\Throwable $e) {
            $log[] = "Pekerja #{$r->user_id} ERROR: " . $e->getMessage();
        }
    }
} catch (\Throwable $e) {
    $log[] = 'Pekerja ERROR: ' . $e->getMessage();
}

echo "=== CRON PENUGASAN ===\n" . $now->format('d/m/Y H:i:s') . "\n";
foreach ($log as $l) echo $l . "\n";
echo "=== SELESAI ===\n";
