<?php

namespace Interdotz\Sdk\Tests\Unit;

use Interdotz\Sdk\DTOs\Auth\SsoCallbackResponse;
use Interdotz\Sdk\Exceptions\AuthException;
use Interdotz\Sdk\InterdotzUrl;
use Interdotz\Sdk\Sso\SsoClient;
use PHPUnit\Framework\TestCase;

class SsoClientTest extends TestCase
{
    private SsoClient $sso;

    protected function setUp(): void
    {
        $this->sso = new SsoClient(clientId: 'test-client-id');
    }

    public function test_getLoginUrl_contains_client_id_and_redirect_url(): void
    {
        $url = $this->sso->getLoginUrl('https://myapp.com/callback');

        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_url=', $url);
        $this->assertStringContainsString(urlencode('https://myapp.com/callback'), $url);
    }

    public function test_getLoginUrl_uses_correct_base_url(): void
    {
        $url = $this->sso->getLoginUrl('https://myapp.com/callback');

        $this->assertStringStartsWith(InterdotzUrl::SSO . '/login', $url);
    }

    public function test_getRegisterUrl_contains_all_params(): void
    {
        $url = $this->sso->getRegisterUrl('https://myapp.com/callback', 'dashboard');

        $this->assertStringStartsWith(InterdotzUrl::SSO . '/register', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state=dashboard', $url);
    }

    public function test_getRegisterUrl_omits_state_when_null(): void
    {
        $url = $this->sso->getRegisterUrl('https://myapp.com/callback');

        $this->assertStringNotContainsString('state=', $url);
    }

    public function test_handleCallback_returns_token_response_on_valid_params(): void
    {
        $result = $this->sso->handleCallback([
            'access_token'  => 'access-abc',
            'refresh_token' => 'refresh-xyz',
            'token_type'    => 'Bearer',
            'state'         => 'dashboard',
        ]);

        $this->assertInstanceOf(SsoCallbackResponse::class, $result);
        $this->assertSame('access-abc', $result->accessToken);
        $this->assertSame('refresh-xyz', $result->refreshToken);
        $this->assertSame('Bearer', $result->tokenType);
        $this->assertSame('dashboard', $result->state);
    }

    public function test_handleCallback_defaults_token_type_to_bearer(): void
    {
        $result = $this->sso->handleCallback([
            'access_token'  => 'access-abc',
            'refresh_token' => 'refresh-xyz',
        ]);

        $this->assertSame('Bearer', $result->tokenType);
    }

    public function test_handleCallback_state_is_null_when_absent(): void
    {
        $result = $this->sso->handleCallback([
            'access_token'  => 'access-abc',
            'refresh_token' => 'refresh-xyz',
        ]);

        $this->assertNull($result->state);
    }

    public function test_handleCallback_throws_auth_exception_when_access_token_missing(): void
    {
        $this->expectException(AuthException::class);

        $this->sso->handleCallback([
            'refresh_token' => 'refresh-xyz',
        ]);
    }

    public function test_handleCallback_throws_auth_exception_when_refresh_token_missing(): void
    {
        $this->expectException(AuthException::class);

        $this->sso->handleCallback([
            'access_token' => 'access-abc',
        ]);
    }
}
