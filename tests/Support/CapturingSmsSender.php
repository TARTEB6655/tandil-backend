<?php

namespace Tests\Support;

use App\Contracts\SmsSender;

final class CapturingSmsSender implements SmsSender
{
    public static ?string $lastMessage = null;

    public static ?string $lastTo = null;

    public function send(string $toPhoneE164, string $message): array
    {
        self::$lastMessage = $message;
        self::$lastTo = $toPhoneE164;

        return [
            'success' => true,
            'provider' => 'test',
            'message_id' => 'test-msg',
            'error' => null,
        ];
    }

    public static function reset(): void
    {
        self::$lastMessage = null;
        self::$lastTo = null;
    }

    public static function extractOtp(): ?string
    {
        if (self::$lastMessage === null) {
            return null;
        }
        if (preg_match('/\b(\d{6})\b/', self::$lastMessage, $m)) {
            return $m[1];
        }

        return null;
    }
}
