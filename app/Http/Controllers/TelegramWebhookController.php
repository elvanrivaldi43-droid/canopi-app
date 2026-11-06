<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
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
