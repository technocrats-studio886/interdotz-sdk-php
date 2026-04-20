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
            transactionId: $data['transaction_id'],
            userId: $data['user_id'],
            coinType: $data['coin_type'],
            amountCharged: $data['amount_charged'],
            balanceBefore: $data['balance_before'],
            balanceAfter: $data['balance_after'],
            referenceType: $data['reference_type'],
            referenceId: $data['reference_id'],
            createdAt: $data['created_at'],
        );
    }
}
