<?php

namespace Interdotz\Sdk\DTOs\Payment;

readonly class BalanceResponse
{
    public function __construct(
        public string $userId,
        public array $balances,
    ) {}

    public function getDotsUnitBalance(): ?int
    {
        foreach ($this->balances as $balance) {
            if ($balance['symbol'] === 'DU') {
                return $balance['balance'];
            }
        }

        return null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            balances: $data['balances'],
        );
    }
}
