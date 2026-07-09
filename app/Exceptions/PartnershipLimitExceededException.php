<?php

namespace App\Exceptions;

use Exception;

class PartnershipLimitExceededException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $limitKey,
        public readonly int $current,
        public readonly ?int $max,
        public readonly ?string $tierSlug = null,
        public readonly bool $upgradeRequired = true,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toErrorPayload(): array
    {
        return [
            'limit' => $this->limitKey,
            'current' => $this->current,
            'max' => $this->max,
            'tier' => $this->tierSlug,
            'upgrade_required' => $this->upgradeRequired,
        ];
    }
}
