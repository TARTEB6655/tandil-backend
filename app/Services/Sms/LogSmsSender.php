<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $toPhoneE164, string $message): array
    {
        Log::channel('stack')->info('SMS (log driver)', [
            'to' => $toPhoneE164,
            'message' => $message,
        ]);

        return [
            'success' => true,
            'provider' => 'log',
            'message_id' => null,
            'error' => null,
        ];
    }
}
