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

    // $tombol = [[['text'=>..,'callback_data'=>..], ...]] (inline_keyboard).
    public function kirimTombol(?string $chatId, string $pesan, array $tombol): bool
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
            $markup = json_encode(['inline_keyboard' => $tombol]);
            $data = $this->kirimRequest($token, $chatId, $pesan, true, $markup);
            if (!($data['ok'] ?? false) && str_contains(strtolower($data['description'] ?? ''), "can't parse entities")) {
                $data = $this->kirimRequest($token, $chatId, $pesan, false, $markup);
            }
            if (!($data['ok'] ?? false)) {
                \Illuminate\Support\Facades\Log::error('TelegramService gagal kirim tombol: ' . ($data['description'] ?? 'unknown error'));
            }
            return $data['ok'] ?? false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TelegramService gagal kirim tombol: ' . $e->getMessage());
            return false;
        }
    }

    // Tutup "loading" di tombol inline + tampilkan toast singkat ke penekan tombol.
    public function jawabTombol(string $callbackId, string $teks): void
    {
        $token = getenv('TELEGRAM_KARYAWAN_TOKEN');
        if (!$token) {
            return;
        }
        try {
            $ch = curl_init("https://api.telegram.org/bot{$token}/answerCallbackQuery");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => ['callback_query_id' => $callbackId, 'text' => $teks],
                CURLOPT_TIMEOUT        => 20,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TelegramService gagal jawabTombol: ' . $e->getMessage());
        }
    }

    // Notifikasi ke Owner (bot owner, token/chat dari environment).
    public function kirimOwner(string $pesan): bool
    {
        try {
            $token = getenv('TELEGRAM_OWNER_TOKEN');
            $chatId = getenv('TELEGRAM_OWNER_CHAT_ID');
            if (!$token || !$chatId) return false;
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['chat_id' => $chatId, 'text' => $pesan], CURLOPT_TIMEOUT => 20,
            ]);
            curl_exec($ch);
            curl_close($ch);
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Telegram owner gagal: ' . $e->getMessage());
            return false;
        }
    }

    private function kirimRequest(string $token, string $chatId, string $pesan, bool $markdown, ?string $replyMarkup = null): array
    {
        $fields = [
            'chat_id' => $chatId,
            'text'    => $pesan,
        ];
        if ($markdown) {
            $fields['parse_mode'] = 'Markdown';
        }
        if ($replyMarkup !== null) {
            $fields['reply_markup'] = $replyMarkup;
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
