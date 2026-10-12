<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;

class TwilioSmsSender implements SmsSender
{
    public function send(string $toPhoneE164, string $message): array
    {
        $sid = (string) config('services.sms.twilio.account_sid');
        $token = (string) config('services.sms.twilio.auth_token');
        $from = (string) config('services.sms.twilio.from');

        if ($sid === '' || $token === '' || $from === '') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'message_id' => null,
                'error' => 'Twilio SMS is not configured.',
            ];
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $toPhoneE164,
                'From' => $from,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            return [
                'success' => false,
                'provider' => 'twilio',
                'message_id' => null,
                'error' => $response->json('message') ?? $response->body(),
            ];
        }

        return [
            'success' => true,
            'provider' => 'twilio',
            'message_id' => $response->json('sid'),
            'error' => null,
        ];
    }
}
