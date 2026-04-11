<?php

namespace Interdotz\Sdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Interdotz\Sdk\DTOs\Payment\BalanceResponse;
use Interdotz\Sdk\DTOs\Payment\ChargeRequestResponse;
use Interdotz\Sdk\DTOs\Payment\ChargeResponse;
use Interdotz\Sdk\Exceptions\InsufficientBalanceException;
use Interdotz\Sdk\Exceptions\PaymentException;
use Interdotz\Sdk\Payment\PaymentClient;
use PHPUnit\Framework\TestCase;

class PaymentClientTest extends TestCase
{
    private function makeClient(array $responses): PaymentClient
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $http    = new Client(['handler' => $handler]);

        return new PaymentClient($http);
    }

    // ── Direct Charge ────────────────────────────────────────────────────────

    public function test_directCharge_returns_charge_response_on_success(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Dots charged successfully',
                'payload' => [
                    'transactionId' => 'txn-001',
                    'userId'        => 'user-123',
                    'coinType'      => 'DU',
                    'amountCharged' => 50,
                    'balanceBefore' => 200,
                    'balanceAfter'  => 150,
                    'referenceType' => 'PURCHASE',
                    'referenceId'   => 'order-001',
                    'createdAt'     => '2024-04-08T10:00:00',
                ],
            ])),
        ]);

        $result = $client->directCharge('token-abc', 50, 'PURCHASE', 'order-001');

        $this->assertInstanceOf(ChargeResponse::class, $result);
        $this->assertSame('txn-001', $result->transactionId);
        $this->assertSame(50, $result->amountCharged);
        $this->assertSame(200, $result->balanceBefore);
        $this->assertSame(150, $result->balanceAfter);
    }

    public function test_directCharge_throws_insufficient_balance_exception_on_422(): void
    {
        $client = $this->makeClient([
            new Response(422, [], json_encode([
                'message' => 'Insufficient balance. Current: 30, required: 50',
                'payload' => null,
            ])),
        ]);

        $this->expectException(InsufficientBalanceException::class);
        $this->expectExceptionCode(422);

        $client->directCharge('token-abc', 50, 'PURCHASE', 'order-001');
    }

    public function test_directCharge_throws_payment_exception_on_duplicate_reference_id(): void
    {
        $client = $this->makeClient([
            new Response(409, [], json_encode([
                'message' => 'Duplicate charge: referenceId order-001 already processed',
                'payload' => null,
            ])),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionCode(409);

        $client->directCharge('token-abc', 50, 'PURCHASE', 'order-001');
    }

    public function test_directCharge_throws_payment_exception_on_unauthorized(): void
    {
        $client = $this->makeClient([
            new Response(401, [], json_encode([
                'message' => 'Token tidak valid atau sudah expired',
                'payload' => null,
            ])),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionCode(401);

        $client->directCharge('expired-token', 50, 'PURCHASE', 'order-001');
    }

    // ── Charge Request ───────────────────────────────────────────────────────

    public function test_createChargeRequest_returns_response_on_success(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Charge request created',
                'payload' => [
                    'token'       => 'charge-token-xyz',
                    'redirectUrl' => 'https://interdotz.com/charge/confirm?token=charge-token-xyz',
                    'expiresAt'   => '2024-04-08T10:15:00',
                ],
            ])),
        ]);

        $result = $client->createChargeRequest(
            accessToken:   'token-abc',
            userId:        'user-123',
            amount:        50,
            referenceType: 'SUBSCRIPTION',
            referenceId:   'sub-premium-april',
            callbackUrl:   'https://myapp.com/payment/callback',
        );

        $this->assertInstanceOf(ChargeRequestResponse::class, $result);
        $this->assertSame('charge-token-xyz', $result->token);
        $this->assertStringContainsString('charge-token-xyz', $result->redirectUrl);
        $this->assertSame('2024-04-08T10:15:00', $result->expiresAt);
    }

    public function test_createChargeRequest_throws_insufficient_balance_exception_on_422(): void
    {
        $client = $this->makeClient([
            new Response(422, [], json_encode([
                'message' => 'Insufficient balance',
                'payload' => null,
            ])),
        ]);

        $this->expectException(InsufficientBalanceException::class);

        $client->createChargeRequest('token-abc', 'user-123', 50, 'PURCHASE', 'order-001', 'https://myapp.com/callback');
    }

    // ── Balance ──────────────────────────────────────────────────────────────

    public function test_getBalance_returns_balance_response_on_success(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Balance retrieved successfully',
                'payload' => [
                    'userId'   => 'user-123',
                    'balances' => [
                        [
                            'coinTypeId'   => 1,
                            'coinTypeName' => 'Dots Unit',
                            'symbol'       => 'DU',
                            'balance'      => 150,
                        ],
                    ],
                ],
            ])),
        ]);

        $result = $client->getBalance('token-abc', 'user-123');

        $this->assertInstanceOf(BalanceResponse::class, $result);
        $this->assertSame('user-123', $result->userId);
        $this->assertSame(150, $result->getDotsUnitBalance());
    }

    public function test_getBalance_returns_null_when_du_not_found(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Balance retrieved successfully',
                'payload' => [
                    'userId'   => 'user-123',
                    'balances' => [],
                ],
            ])),
        ]);

        $result = $client->getBalance('token-abc', 'user-123');

        $this->assertNull($result->getDotsUnitBalance());
    }
}
