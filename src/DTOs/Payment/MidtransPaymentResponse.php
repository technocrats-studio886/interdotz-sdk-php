<?php

namespace Interdotz\Sdk\DTOs\Payment;

readonly class MidtransPaymentResponse
{
    public function __construct(
        public string $id,
        public string $referenceId,
        public int $amount,
        public string $currency,
        public string $status,
        public ?string $snapToken,
        public ?string $redirectUrl,
        public ?string $expiresAt,
        public string $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:          $data['id'],
            referenceId: $data['reference_id'],
            amount:      $data['amount'],
            currency:    $data['currency'],
            status:      $data['status'],
            snapToken:   $data['snap_token'] ?? null,
            redirectUrl: $data['redirect_url'] ?? null,
            expiresAt:   $data['expires_at'] ?? null,
            createdAt:   $data['created_at'],
        );
    }
}
