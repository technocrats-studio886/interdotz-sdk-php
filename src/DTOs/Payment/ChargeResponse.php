<?php

namespace Interdotz\Sdk\DTOs\Payment;

readonly class ChargeResponse
{
    public function __construct(
        public string $transactionId,
        public string $userId,
        public string $coinType,
        public int $amountCharged,
        public int $balanceBefore,
        public int $balanceAfter,
        public string $referenceType,
        public string $referenceId,
        public string $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            transactionId: $data['transactionId'],
            userId: $data['userId'],
            coinType: $data['coinType'],
            amountCharged: $data['amountCharged'],
            balanceBefore: $data['balanceBefore'],
            balanceAfter: $data['balanceAfter'],
            referenceType: $data['referenceType'],
            referenceId: $data['referenceId'],
            createdAt: $data['createdAt'],
        );
    }
}
