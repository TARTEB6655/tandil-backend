<?php

namespace App\Support;

final class TwilioHttpCredentials
{
    /**
     * @return array{0: string, 1: string}|null username and password for HTTP Basic auth
     */
    public static function basicAuth(): ?array
    {
        $accountSid = (string) config('services.sms.twilio.account_sid');
        $apiKeySid = (string) config('services.sms.twilio.api_key_sid');
        $apiKeySecret = (string) config('services.sms.twilio.api_key_secret');
        $authToken = (string) config('services.sms.twilio.auth_token');

        if ($apiKeySid !== '' && $apiKeySecret !== '') {
            return [$apiKeySid, $apiKeySecret];
        }

        if ($accountSid !== '' && $authToken !== '') {
            return [$accountSid, $authToken];
        }

        return null;
    }

    public static function isVerifyConfigured(): bool
    {
        $serviceSid = (string) config('services.sms.twilio.verify_service_sid');

        return $serviceSid !== '' && self::basicAuth() !== null;
    }
}
