<?php

namespace Interdotz\Sdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Interdotz\Sdk\DTOs\Payment\MidtransPaymentResponse;
use Interdotz\Sdk\DTOs\Payment\MidtransPaymentStatusResponse;
use Interdotz\Sdk\Exceptions\PaymentException;
use Interdotz\Sdk\Payment\PaymentClient;
use Interdotz\Sdk\Webhook\WebhookHandler;
use PHPUnit\Framework\TestCase;

class MidtransPaymentTest extends TestCase
{
    private function makeClient(array $responses): PaymentClient
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $http    = new Client(['handler' => $handler]);

        return new PaymentClient($http);
    }

    // ── Create Payment ────────────────────────────────────────────────────────

    public function test_createMidtransPayment_returns_response_with_snap_token(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Payment created successfully',
                'payload' => [
                    'id'           => 'pay-001',
                    'reference_id' => 'order-001',
                    'amount'       => 150000,
                    'currency'     => 'IDR',
                    'status'       => 'PENDING',
                    'snap_token'   => 'snap-token-xyz',
                    'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz',
                    'expires_at'   => '2024-04-09T10:00:00',
                    'created_at'   => '2024-04-08T10:00:00',
                ],
            ])),
        ]);

        $result = $client->createMidtransPayment(
            accessToken:  'token-abc',
            referenceId:  'order-001',
            amount:       150000,
            items:        [
                ['id' => 'item-1', 'name' => 'Premium Plan', 'price' => 150000, 'quantity' => 1],
            ],
            redirectUrl:  'https://myapp.com/payment/callback',
            customer:     ['name' => 'John Doe', 'email' => 'john@example.com'],
        );

        $this->assertInstanceOf(MidtransPaymentResponse::class, $result);
        $this->assertSame('pay-001', $result->id);
        $this->assertSame('order-001', $result->referenceId);
        $this->assertSame(150000, $result->amount);
        $this->assertSame('IDR', $result->currency);
        $this->assertSame('PENDING', $result->status);
        $this->assertSame('snap-token-xyz', $result->snapToken);
        $this->assertNotNull($result->redirectUrl);
    }

    public function test_createMidtransPayment_works_without_optional_fields(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Payment created successfully',
                'payload' => [
                    'id'           => 'pay-002',
                    'reference_id' => 'order-002',
                    'amount'       => 50000,
                    'currency'     => 'IDR',
                    'status'       => 'PENDING',
                    'snap_token'   => 'snap-token-abc',
                    'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/snap-token-abc',
                    'expires_at'   => null,
                    'created_at'   => '2024-04-08T10:00:00',
                ],
            ])),
        ]);

        $result = $client->createMidtransPayment(
            accessToken: 'token-abc',
            referenceId: 'order-002',
            amount:      50000,
        );

        $this->assertSame('pay-002', $result->id);
        $this->assertNull($result->expiresAt);
    }

    public function test_createMidtransPayment_throws_payment_exception_on_error(): void
    {
        $client = $this->makeClient([
            new Response(409, [], json_encode([
                'message' => 'Duplicate reference_id',
                'payload' => null,
            ])),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionCode(409);

        $client->createMidtransPayment('token-abc', 'order-001', 50000);
    }

    // ── Get Payment Status ────────────────────────────────────────────────────

    public function test_getMidtransPaymentStatus_returns_status_response(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Payment status retrieved',
                'payload' => [
                    'id'                     => 'pay-001',
                    'reference_id'           => 'order-001',
                    'amount'                 => 150000,
                    'currency'               => 'IDR',
                    'status'                 => 'SETTLEMENT',
                    'payment_method'         => 'bank_transfer',
                    'gateway_transaction_id' => 'mid-txn-001',
                    'paid_at'                => '2024-04-08T10:05:00',
                    'created_at'             => '2024-04-08T10:00:00',
                    'updated_at'             => '2024-04-08T10:05:00',
                ],
            ])),
        ]);

        $result = $client->getMidtransPaymentStatus('token-abc', 'pay-001');

        $this->assertInstanceOf(MidtransPaymentStatusResponse::class, $result);
        $this->assertSame('SETTLEMENT', $result->status);
        $this->assertSame('bank_transfer', $result->paymentMethod);
        $this->assertSame('mid-txn-001', $result->gatewayTransactionId);
        $this->assertTrue($result->isSettled());
        $this->assertFalse($result->isPending());
        $this->assertFalse($result->isFailed());
    }

    public function test_getMidtransPaymentStatus_pending_status(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Payment status retrieved',
                'payload' => [
                    'id'                     => 'pay-001',
                    'reference_id'           => 'order-001',
                    'amount'                 => 150000,
                    'currency'               => 'IDR',
                    'status'                 => 'PENDING',
                    'payment_method'         => null,
                    'gateway_transaction_id' => null,
                    'paid_at'                => null,
                    'created_at'             => '2024-04-08T10:00:00',
                    'updated_at'             => '2024-04-08T10:00:00',
                ],
            ])),
        ]);

        $result = $client->getMidtransPaymentStatus('token-abc', 'pay-001');

        $this->assertTrue($result->isPending());
        $this->assertFalse($result->isSettled());
        $this->assertNull($result->paymentMethod);
        $this->assertNull($result->paidAt);
    }

    public function test_getMidtransPaymentStatus_failed_statuses(): void
    {
        foreach (['FAILED', 'EXPIRE', 'CANCEL'] as $failedStatus) {
            $client = $this->makeClient([
                new Response(200, [], json_encode([
                    'message' => 'Payment status retrieved',
                    'payload' => [
                        'id'                     => 'pay-001',
                        'reference_id'           => 'order-001',
                        'amount'                 => 150000,
                        'currency'               => 'IDR',
                        'status'                 => $failedStatus,
                        'payment_method'         => null,
                        'gateway_transaction_id' => null,
                        'paid_at'                => null,
                        'created_at'             => '2024-04-08T10:00:00',
                        'updated_at'             => '2024-04-08T10:00:00',
                    ],
                ])),
            ]);

            $result = $client->getMidtransPaymentStatus('token-abc', 'pay-001');

            $this->assertTrue($result->isFailed(), "Expected isFailed() for status: {$failedStatus}");
        }
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    public function test_webhook_isPaymentSettlement_returns_true_for_settlement_event(): void
    {
        $handler = new WebhookHandler();

        $payload = $handler->parse(json_encode([
            'event'     => 'payment.settlement',
            'timestamp' => '2024-04-08T10:05:00',
            'data'      => [
                'payment_id'             => 'pay-001',
                'reference_id'           => 'order-001',
                'amount'                 => 150000,
                'status'                 => 'SETTLEMENT',
                'payment_method'         => 'bank_transfer',
                'gateway_transaction_id' => 'mid-txn-001',
                'paid_at'                => '2024-04-08T10:05:00',
            ],
        ]));

        $this->assertTrue($payload->isPaymentSettlement());
        $this->assertFalse($payload->isPaymentFailed());
        $this->assertFalse($payload->isSuccess());
        $this->assertFalse($payload->isFailed());
    }

    public function test_webhook_isPaymentFailed_returns_true_for_failed_event(): void
    {
        $handler = new WebhookHandler();

        $payload = $handler->parse(json_encode([
            'event'     => 'payment.failed',
            'timestamp' => '2024-04-08T10:05:00',
            'data'      => [
                'payment_id'   => 'pay-001',
                'reference_id' => 'order-001',
                'amount'       => 150000,
                'status'       => 'FAILED',
            ],
        ]));

        $this->assertTrue($payload->isPaymentFailed());
        $this->assertFalse($payload->isPaymentSettlement());
    }
}
