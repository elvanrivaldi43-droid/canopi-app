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
            return false;
        }

        try {
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'chat_id'    => $chatId,
                    'text'       => $pesan,
                    'parse_mode' => 'Markdown',
                ],
                CURLOPT_TIMEOUT => 20,
            ]);
            $result = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($result, true);
            return $data['ok'] ?? false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TelegramService gagal kirim: ' . $e->getMessage());
            return false;
        }
    }
}
