<?php

namespace Interdotz\Sdk\DTOs\Auth;

readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['accessToken'],
            expiresIn: $data['expiresIn'],
        );
    }
}
