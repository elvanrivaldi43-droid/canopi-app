<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
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

    public static function parseStartToken(string $text): ?string
    {
        if (preg_match('/^\/start\s+(\S+)$/', trim($text), $m)) {
            return $m[1];
        }
        return null;
    }
}
