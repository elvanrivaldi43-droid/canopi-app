<?php

namespace App\Services;

class TelegramService
{
    public function kirim(?string $chatId, string $pesan): bool
    {
        if (!$chatId) {
            return false;
        }

        $token = getenv('TELEGRAM_KARYAWAN_TOKEN');
        if (!$token) {
            \Illuminate\Support\Facades\Log::error('TelegramService: TELEGRAM_KARYAWAN_TOKEN tidak diset');
            return false;
        }

        try {
            $data = $this->kirimRequest($token, $chatId, $pesan, true);

            // Markdown yang tak seimbang (_, *, [, `) bikin Telegram tolak SELURUH pesan.
            // Coba ulang sekali tanpa parse_mode biar pesan tetap terkirim (plain text).
            if (!($data['ok'] ?? false) && str_contains(strtolower($data['description'] ?? ''), "can't parse entities")) {
                $data = $this->kirimRequest($token, $chatId, $pesan, false);
            }

            if (!($data['ok'] ?? false)) {
                \Illuminate\Support\Facades\Log::error('TelegramService gagal kirim: ' . ($data['description'] ?? 'unknown error'));
            }

            return $data['ok'] ?? false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TelegramService gagal kirim: ' . $e->getMessage());
            return false;
        }
    }

    private function kirimRequest(string $token, string $chatId, string $pesan, bool $markdown): array
    {
        $fields = [
            'chat_id' => $chatId,
            'text'    => $pesan,
        ];
        if ($markdown) {
            $fields['parse_mode'] = 'Markdown';
        }

        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true) ?? [];
    }
}
