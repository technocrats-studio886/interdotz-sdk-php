<?php

namespace Interdotz\Sdk\DTOs\Payment;

readonly class ChargeRequestResponse
{
    public function __construct(
        public string $token,
        public string $redirectUrl,
        public string $expiresAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            redirectUrl: $data['redirectUrl'],
            expiresAt: $data['expiresAt'],
        );
    }
}
