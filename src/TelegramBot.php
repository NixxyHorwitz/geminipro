<?php
declare(strict_types=1);

namespace App;

class TelegramBot
{
    private string $token;
    private string $apiBase;

    public function __construct(string $token)
    {
        $this->token   = $token;
        $this->apiBase = "https://api.telegram.org/bot{$token}";
    }

    // ------------------------------------------------------------------
    // Webhook
    // ------------------------------------------------------------------

    public function setWebhook(string $url, string $secret = ''): array
    {
        return $this->call('setWebhook', [
            'url'          => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', []);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', []);
    }

    // ------------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------------

    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public function sendPhoto(int|string $chatId, string $photoPath, string $caption = '', array $extra = []): array
    {
        $url = "{$this->apiBase}/sendPhoto";

        $fields = array_merge([
            'chat_id'    => $chatId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ], $extra);

        if (str_starts_with($photoPath, 'http')) {
            $fields['photo'] = $photoPath;
            return $this->call('sendPhoto', $fields);
        }

        // Multipart upload
        $fields['photo'] = new \CURLFile($photoPath);
        return $this->callMultipart($url, $fields);
    }

    public function sendDocument(int|string $chatId, string $filePath, string $caption = '', array $extra = []): array
    {
        $url    = "{$this->apiBase}/sendDocument";
        $fields = array_merge([
            'chat_id'  => $chatId,
            'caption'  => $caption,
            'parse_mode' => 'HTML',
            'document' => new \CURLFile($filePath),
        ], $extra);
        return $this->callMultipart($url, $fields);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): array
    {
        return $this->call('editMessageText', array_merge([
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert,
        ]);
    }

    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->call('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    // ------------------------------------------------------------------
    // Inline keyboards builder
    // ------------------------------------------------------------------

    public static function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    public static function btn(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    public static function urlBtn(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    // ------------------------------------------------------------------
    // API caller
    // ------------------------------------------------------------------

    public function call(string $method, array $params): array
    {
        $ch = curl_init("{$this->apiBase}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response ?: '{}', true) ?? [];
    }

    private function callMultipart(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response ?: '{}', true) ?? [];
    }

    // ------------------------------------------------------------------
    // Validate incoming secret
    // ------------------------------------------------------------------

    public function validateSecret(string $headerSecret, string $expectedSecret): bool
    {
        if ($expectedSecret === '') return true;
        return hash_equals($expectedSecret, $headerSecret);
    }
}
