<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PenugasanAturan;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if (!self::secretValid(
            $request->header('X-Telegram-Bot-Api-Secret-Token'),
            (string) (getenv('TELEGRAM_WEBHOOK_SECRET') ?: ($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? ''))
        )) {
            return response('Forbidden', 403);
        }

        if ($request->has('callback_query')) {
            try {
                $this->prosesJawaban((array) $request->input('callback_query'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Webhook jawaban hadir gagal: ' . $e->getMessage());
            }
            return response('OK', 200);
        }

        $text   = $request->input('message.text', '');
        $chatId = $request->input('message.chat.id');

        $token = self::parseStartToken($text);

        if ($token && $chatId) {
            $user = User::where('telegram_link_token', $token)->first();

            if ($user) {
                $user->telegram_chat_id    = (string) $chatId;
                $user->telegram_link_token = null;
                $user->save();

                app(TelegramService::class)->kirim(
                    (string) $chatId,
                    "✅ Berhasil terhubung, {$user->name}! Mulai sekarang notifikasi CanopiBSD akan masuk ke sini."
                );
            }
        }

        return response('OK', 200);
    }

    private function prosesJawaban(array $cb): void
    {
        $tg = app(TelegramService::class);
        $cbId = (string) ($cb['id'] ?? '');
        $tombol = PenugasanAturan::parseTombol((string) ($cb['data'] ?? ''));
        if (!$tombol) return;

        $row = DB::table('konfirmasi_hadir')->where('id', $tombol['id'])->first();
        if (!$row) { $tg->jawabTombol($cbId, 'Konfirmasi tidak ditemukan.'); return; }

        $pemilik = DB::table('users')->where('id', $row->user_id)->first(['name', 'telegram_chat_id']);
        $tolak = PenugasanAturan::alasanTolakJawaban(
            isset($cb['from']['id']) ? (string) $cb['from']['id'] : null,
            $pemilik->telegram_chat_id ?? null,
            PenugasanAturan::bolehJawab((string) $row->tanggal, now())
        );
        if ($tolak) { $tg->jawabTombol($cbId, $tolak); return; }

        $lama = $row->jawaban;
        DB::table('konfirmasi_hadir')->where('id', $row->id)
            ->update(['jawaban' => $tombol['jawaban'], 'dijawab_at' => now(), 'updated_at' => now()]);
        $tg->jawabTombol($cbId, $tombol['jawaban'] === 'hadir' ? 'Tercatat: Hadir' : 'Tercatat: Tidak Hadir (libur tanpa gaji)');

        if ($tombol['jawaban'] !== 'tidak_hadir' || $lama === 'tidak_hadir') return;

        $tgl = \Carbon\Carbon::parse($row->tanggal);
        $tugas = DB::table('penugasan_harian as h')
            ->join('project_tahap as t', 't.id', '=', 'h.project_tahap_id')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->where('h.tanggal', $row->tanggal)->where('h.user_id', $row->user_id)
            ->get(['t.nama_tahap', 'p.nama_project', 'p.nama_customer'])
            ->map(fn($r) => ($r->nama_project ?: ($r->nama_customer ?: 'Project')) . ' - ' . $r->nama_tahap)
            ->implode(', ');
        $pesan = "{$pemilik->name} TIDAK HADIR besok (" . $tgl->translatedFormat('d F Y') . ')'
            . ($tugas !== '' ? " — {$tugas}" : '') . '. Cari pengganti.';

        $mandorId = DB::table('setting_penugasan')->value('mandor_bertugas_id');
        $mandorChat = $mandorId ? DB::table('users')->where('id', $mandorId)->value('telegram_chat_id') : null;
        if ($mandorChat) $tg->kirim((string) $mandorChat, $pesan);
        $tg->kirimOwner($pesan);
    }

    // Gagal tertutup: secret server kosong/<16 karakter = tolak semua (sama dgn CronAuth).
    public static function secretValid(?string $header, ?string $server): bool
    {
        if (strlen((string) $server) < \App\Services\CronAuth::MIN_PANJANG) return false;

        return hash_equals((string) $server, (string) $header);
    }

    public static function parseStartToken(string $text): ?string
    {
        if (preg_match('/^\/start\s+(\S+)$/', trim($text), $m)) {
            return $m[1];
        }
        return null;
    }
}
