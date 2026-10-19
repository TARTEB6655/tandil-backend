<?php

namespace App\Console\Commands;

use App\Services\Auth\TwilioVerifyPasswordResetOtpHandler;
use App\Support\PhoneNumberNormalizer;
use App\Support\TwilioHttpCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SmokeTwilioVerifyCommand extends Command
{
    protected $signature = 'sms:verify-smoke {phone : E.164 or local mobile, e.g. +919816025872}';

    protected $description = 'Smoke-test Twilio Verify (config + start verification). Run on the server with production .env.';

    public function handle(TwilioVerifyPasswordResetOtpHandler $verify): int
    {
        $driver = (string) config('services.sms.driver', 'log');
        $this->line('SMS_DRIVER='.$driver);

        if ($driver !== 'twilio_verify') {
            $this->warn('Expected SMS_DRIVER=twilio_verify on production. OTP SMS uses Twilio Verify only with that driver.');
        }

        $accountSid = (string) config('services.sms.twilio.account_sid');
        $serviceSid = (string) config('services.sms.twilio.verify_service_sid');
        $this->table(['Key', 'Value'], [
            ['TWILIO_ACCOUNT_SID', $accountSid !== '' ? substr($accountSid, 0, 6).'…' : '(empty)'],
            ['TWILIO_VERIFY_SERVICE_SID', $serviceSid !== '' ? $serviceSid : '(empty)'],
            ['Auth', TwilioHttpCredentials::basicAuth() !== null ? 'configured' : 'MISSING'],
            ['API key pair', $this->apiKeyStatus()],
        ]);

        if (! TwilioHttpCredentials::isVerifyConfigured()) {
            $this->error('Twilio Verify is not fully configured (service SID + auth token or API key secret).');

            return self::FAILURE;
        }

        $e164 = PhoneNumberNormalizer::toE164($this->argument('phone'));
        if ($e164 === null) {
            $this->error('Could not parse phone to E.164.');

            return self::FAILURE;
        }
        $this->info('E.164 destination: '.$e164);

        [$user, $pass] = TwilioHttpCredentials::basicAuth();
        $response = Http::withBasicAuth($user, $pass)
            ->asForm()
            ->post("https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications", [
                'To' => $e164,
                'Channel' => 'sms',
            ]);

        $this->line('HTTP status: '.$response->status());
        $body = $response->json();
        if (is_array($body)) {
            $this->line('Twilio status: '.($body['status'] ?? '(none)'));
            $this->line('Twilio message: '.($body['message'] ?? '(none)'));
            if (isset($body['code'])) {
                $this->line('Twilio code: '.$body['code']);
            }
        } else {
            $this->line($response->body());
        }

        if ($response->successful()) {
            $this->info('Smoke OK — verification started (SMS should be sent if geo permissions allow this country).');

            return self::SUCCESS;
        }

        $this->error('Smoke FAILED — fix Twilio error above (often: enable country in Verify Geo permissions, or invalid auth).');

        return self::FAILURE;
    }

    private function apiKeyStatus(): string
    {
        $sid = (string) config('services.sms.twilio.api_key_sid');
        $secret = (string) config('services.sms.twilio.api_key_secret');
        if ($sid === '' && $secret === '') {
            return 'not used (auth token)';
        }
        if ($sid !== '' && $secret !== '') {
            return 'using API key';
        }

        return 'INCOMPLETE (set TWILIO_API_KEY_SECRET or remove TWILIO_API_KEY_SID)';
    }
}
