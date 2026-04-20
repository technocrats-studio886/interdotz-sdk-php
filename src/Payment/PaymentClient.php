<?php

namespace Interdotz\Sdk\Payment;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Interdotz\Sdk\DTOs\Payment\BalanceResponse;
use Interdotz\Sdk\DTOs\Payment\ChargeRequestResponse;
use Interdotz\Sdk\DTOs\Payment\ChargeResponse;
use Interdotz\Sdk\DTOs\Payment\MidtransPaymentResponse;
use Interdotz\Sdk\DTOs\Payment\MidtransPaymentStatusResponse;
use Interdotz\Sdk\Exceptions\InsufficientBalanceException;
use Interdotz\Sdk\Exceptions\PaymentException;

class PaymentClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {}

    public function directCharge(
        string $accessToken,
        int $amount,
        string $referenceType,
        string $referenceId,
    ): ChargeResponse {
        try {
            $response = $this->httpClient->request('POST', '/api/client/charge', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'json'    => [
                    'amount'         => $amount,
                    'reference_type' => $referenceType,
                    'reference_id'   => $referenceId,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return ChargeResponse::fromArray($body['payload']);
        } catch (ClientException $e) {
            $this->handlePaymentException($e);
        }
    }

    public function createChargeRequest(
        string $accessToken,
        string $userId,
        int $amount,
        string $referenceType,
        string $referenceId,
        string $redirectUrl,
        ?string $description = null,
        ?string $productLogo = null,
    ): ChargeRequestResponse {
        try {
            $payload = [
                'user_id'        => $userId,
                'amount'         => $amount,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'redirect_url'   => $redirectUrl,
            ];

            if ($description !== null) {
                $payload['description'] = $description;
            }

            if ($productLogo !== null) {
                $payload['product_logo'] = $productLogo;
            }

            $response = $this->httpClient->request('POST', '/api/client/charge/request', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'json'    => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return ChargeRequestResponse::fromArray($body['payload']);
        } catch (ClientException $e) {
            $this->handlePaymentException($e);
        }
    }

    public function getBalance(string $accessToken, string $userId): BalanceResponse
    {
        try {
            $response = $this->httpClient->request('GET', '/api/client/balance', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'query'   => ['user_id' => $userId],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return BalanceResponse::fromArray($body['payload']);
        } catch (ClientException $e) {
            $this->handlePaymentException($e);
        }
    }

    public function createMidtransPayment(
        string $accessToken,
        string $referenceId,
        int $amount,
        array $items = [],
        ?string $redirectUrl = null,
        ?array $customer = null,
        string $currency = 'IDR',
    ): MidtransPaymentResponse {
        try {
            $payload = [
                'reference_id' => $referenceId,
                'amount'       => $amount,
                'currency'     => $currency,
                'items'        => $items,
            ];

            if ($redirectUrl !== null) {
                $payload['redirect_url'] = $redirectUrl;
            }

            if ($customer !== null) {
                $payload['customer'] = $customer;
            }

            $response = $this->httpClient->request('POST', '/api/client/payments', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'json'    => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return MidtransPaymentResponse::fromArray($body['payload']);
        } catch (ClientException $e) {
            $this->handlePaymentException($e);
        }
    }

    public function getMidtransPaymentStatus(
        string $accessToken,
        string $paymentId,
    ): MidtransPaymentStatusResponse {
        try {
            $response = $this->httpClient->request('GET', "/api/client/payments/{$paymentId}", [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return MidtransPaymentStatusResponse::fromArray($body['payload']);
        } catch (ClientException $e) {
            $this->handlePaymentException($e);
        }
    }

    private function handlePaymentException(ClientException $e): never
    {
        $body       = json_decode($e->getResponse()->getBody()->getContents(), true);
        $statusCode = $e->getResponse()->getStatusCode();
        $message    = $body['message'] ?? 'Payment request failed';

        if ($statusCode === 422) {
            throw new InsufficientBalanceException(
                message: $message,
                context: $body,
                code: $statusCode,
                previous: $e,
            );
        }

        throw new PaymentException(
            message: $message,
            context: $body,
            code: $statusCode,
            previous: $e,
        );
    }
}
