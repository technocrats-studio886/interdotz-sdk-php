<?php

namespace Interdotz\Sdk\DTOs\Auth;

use Interdotz\Sdk\Exceptions\AuthException;

readonly class SsoCallbackResponse
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenType,
        public ?string $state,
    ) {}

    public static function fromQueryParams(array $params): self
    {
        if (empty($params['access_token']) || empty($params['refresh_token'])) {
            throw new AuthException('Invalid SSO callback: missing tokens');
        }

        return new self(
            accessToken:  $params['access_token'],
            refreshToken: $params['refresh_token'],
            tokenType:    $params['token_type'] ?? 'Bearer',
            state:        $params['state'] ?? null,
        );
    }
}
