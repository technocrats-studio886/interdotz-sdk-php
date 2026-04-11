<?php

namespace Interdotz\Sdk\Webhook;

readonly class WebhookPayload
{
    public function __construct(
        public string $event,
        public string $timestamp,
        public array $data,
    ) {}

    public function isSuccess(): bool
    {
        return $this->event === 'charge.success';
    }

    public function isFailed(): bool
    {
        return $this->event === 'charge.failed';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            event:     $data['event'],
            timestamp: $data['timestamp'],
            data:      $data['data'],
        );
    }
}
