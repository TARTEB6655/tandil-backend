<?php

namespace App\Contracts;

interface SmsSender
{
    /**
     * @return array{success: bool, provider: string, message_id: ?string, error: ?string}
     */
    public function send(string $toPhoneE164, string $message): array;
}
