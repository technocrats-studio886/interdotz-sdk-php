<?php

namespace Interdotz\Sdk\DTOs\Payment;

readonly class MidtransPaymentStatusResponse
{
    public function __construct(
        public string $id,
        public string $referenceId,
        public int $amount,
        public string $currency,
        public string $status,
        public ?string $paymentMethod,
        public ?string $gatewayTransactionId,
        public ?string $paidAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public function isSettled(): bool
    {
        return $this->status === 'SETTLEMENT';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['FAILED', 'EXPIRE', 'CANCEL'], true);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:                   $data['id'],
            referenceId:          $data['reference_id'],
            amount:               $data['amount'],
            currency:             $data['currency'],
            status:               $data['status'],
            paymentMethod:        $data['payment_method'] ?? null,
            gatewayTransactionId: $data['gateway_transaction_id'] ?? null,
            paidAt:               $data['paid_at'] ?? null,
            createdAt:            $data['created_at'],
            updatedAt:            $data['updated_at'],
        );
    }
}
