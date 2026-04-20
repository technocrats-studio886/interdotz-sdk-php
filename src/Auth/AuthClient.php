<?php

namespace Interdotz\Sdk\Auth;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Interdotz\Sdk\DTOs\Auth\TokenResponse;
use Interdotz\Sdk\Exceptions\AuthException;

class AuthClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function authenticate(string $userId): TokenResponse
    {
        try {
            $response = $this->httpClient->request('POST', '/api/client/auth', [
                'json' => [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'user_id'       => $userId,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return TokenResponse::fromArray($body['payload']);
        } catch (ClientException $e) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);

            throw new AuthException(
                message: $body['message'] ?? 'Authentication failed',
                context: $body,
                code: $e->getResponse()->getStatusCode(),
                previous: $e,
            );
        }
    }
}
