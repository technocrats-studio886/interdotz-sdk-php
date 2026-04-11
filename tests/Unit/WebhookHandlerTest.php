<?php

namespace Interdotz\Sdk\Tests\Unit;

use Interdotz\Sdk\Exceptions\InterdotzException;
use Interdotz\Sdk\Webhook\WebhookHandler;
use Interdotz\Sdk\Webhook\WebhookPayload;
use PHPUnit\Framework\TestCase;

class WebhookHandlerTest extends TestCase
{
    private WebhookHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new WebhookHandler();
    }

    private function makeSuccessPayload(array $overrides = []): string
    {
        return json_encode(array_merge([
            'event'     => 'charge.success',
            'timestamp' => '2024-04-08T10:00:00',
            'data'      => [
                'transactionId' => 'txn-001',
                'userId'        => 'user-123',
                'amount'        => 50,
                'referenceType' => 'PURCHASE',
                'referenceId'   => 'order-001',
                'coinType'      => 'DU',
                'balanceBefore' => 200,
                'balanceAfter'  => 150,
                'errorMessage'  => null,
            ],
        ], $overrides));
    }

    private function makeFailedPayload(): string
    {
        return json_encode([
            'event'     => 'charge.failed',
            'timestamp' => '2024-04-08T10:00:00',
            'data'      => [
                'transactionId' => null,
                'userId'        => 'user-123',
                'amount'        => 50,
                'referenceType' => 'PURCHASE',
                'referenceId'   => 'order-001',
                'coinType'      => null,
                'balanceBefore' => null,
                'balanceAfter'  => null,
                'errorMessage'  => 'Insufficient balance. Current: 30, required: 50',
            ],
        ]);
    }

    // ── Parse ────────────────────────────────────────────────────────────────

    public function test_parse_returns_webhook_payload_on_valid_json(): void
    {
        $result = $this->handler->parse($this->makeSuccessPayload());

        $this->assertInstanceOf(WebhookPayload::class, $result);
        $this->assertSame('charge.success', $result->event);
        $this->assertSame('2024-04-08T10:00:00', $result->timestamp);
        $this->assertSame('order-001', $result->data['referenceId']);
    }

    public function test_parse_throws_exception_on_invalid_json(): void
    {
        $this->expectException(InterdotzException::class);
        $this->expectExceptionMessage('Invalid webhook payload: not valid JSON');

        $this->handler->parse('not-valid-json{{{');
    }

    public function test_parse_throws_exception_when_event_missing(): void
    {
        $this->expectException(InterdotzException::class);
        $this->expectExceptionMessage('Invalid webhook payload: missing required fields');

        $this->handler->parse(json_encode(['data' => []]));
    }

    public function test_parse_throws_exception_when_data_missing(): void
    {
        $this->expectException(InterdotzException::class);

        $this->handler->parse(json_encode(['event' => 'charge.success']));
    }

    // ── isSuccess / isFailed ─────────────────────────────────────────────────

    public function test_isSuccess_returns_true_for_charge_success_event(): void
    {
        $payload = $this->handler->parse($this->makeSuccessPayload());

        $this->assertTrue($payload->isSuccess());
        $this->assertFalse($payload->isFailed());
    }

    public function test_isFailed_returns_true_for_charge_failed_event(): void
    {
        $payload = $this->handler->parse($this->makeFailedPayload());

        $this->assertTrue($payload->isFailed());
        $this->assertFalse($payload->isSuccess());
    }

    // ── Data Access ───────────────────────────────────────────────────────────

    public function test_parse_exposes_transaction_data(): void
    {
        $payload = $this->handler->parse($this->makeSuccessPayload());

        $this->assertSame('txn-001', $payload->data['transactionId']);
        $this->assertSame(50, $payload->data['amount']);
        $this->assertSame(150, $payload->data['balanceAfter']);
    }

    public function test_parse_exposes_error_message_on_failed_event(): void
    {
        $payload = $this->handler->parse($this->makeFailedPayload());

        $this->assertStringContainsString('Insufficient balance', $payload->data['errorMessage']);
    }
}
