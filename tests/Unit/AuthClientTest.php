<?php

namespace Interdotz\Sdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Interdotz\Sdk\Auth\AuthClient;
use Interdotz\Sdk\DTOs\Auth\TokenResponse;
use Interdotz\Sdk\Exceptions\AuthException;
use PHPUnit\Framework\TestCase;

class AuthClientTest extends TestCase
{
    private function makeClient(array $responses): AuthClient
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $http    = new Client(['handler' => $handler]);

        return new AuthClient($http, 'test-client-id', 'test-client-secret');
    }

    public function test_authenticate_returns_token_response_on_success(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'message' => 'Authentication successful',
                'payload' => [
                    'accessToken' => 'eyJhbGci.test.token',
                    'expiresIn'   => 3600,
                ],
            ])),
        ]);

        $result = $client->authenticate('eyJhbGci.user.jwt');

        $this->assertInstanceOf(TokenResponse::class, $result);
        $this->assertSame('eyJhbGci.test.token', $result->accessToken);
        $this->assertSame(3600, $result->expiresIn);
    }

    public function test_authenticate_throws_auth_exception_on_invalid_credentials(): void
    {
        $client = $this->makeClient([
            new Response(401, [], json_encode([
                'message' => 'Invalid client credentials',
                'payload' => null,
            ])),
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid client credentials');
        $this->expectExceptionCode(401);

        $client->authenticate('eyJhbGci.user.jwt');
    }

    public function test_authenticate_throws_auth_exception_on_invalid_user_jwt(): void
    {
        $client = $this->makeClient([
            new Response(401, [], json_encode([
                'message' => 'Invalid token',
                'payload' => null,
            ])),
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid token');
        $this->expectExceptionCode(401);

        $client->authenticate('invalid.jwt.token');
    }

    public function test_authenticate_throws_auth_exception_on_inactive_client(): void
    {
        $client = $this->makeClient([
            new Response(403, [], json_encode([
                'message' => 'Client is inactive',
                'payload' => null,
            ])),
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionCode(403);

        $client->authenticate('eyJhbGci.user.jwt');
    }

    public function test_authenticate_exception_carries_response_context(): void
    {
        $client = $this->makeClient([
            new Response(401, [], json_encode([
                'message' => 'Invalid client credentials',
                'payload' => null,
            ])),
        ]);

        try {
            $client->authenticate('eyJhbGci.user.jwt');
            $this->fail('Expected AuthException was not thrown');
        } catch (AuthException $e) {
            $this->assertNotNull($e->getContext());
            $this->assertSame('Invalid client credentials', $e->getContext()['message']);
        }
    }
}
