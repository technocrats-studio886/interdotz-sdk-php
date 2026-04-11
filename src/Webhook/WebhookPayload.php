<?php

namespace Interdotz\Sdk\Webhook;

readonly class WebhookPayload
{
    public function __construct(
        public string $event,
        public string $timestamp,
        public array $data,
    ) {}

    // ── Dots Unit events ─────────────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return $this->event === 'charge.success';
    }

    public function isFailed(): bool
    {
        return $this->event === 'charge.failed';
    }

    // ── Midtrans payment events ───────────────────────────────────────────────

    public function isPaymentSettlement(): bool
    {
        return $this->event === 'payment.settlement';
    }

    public function isPaymentFailed(): bool
    {
        return $this->event === 'payment.failed';
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
