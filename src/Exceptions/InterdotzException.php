<?php

namespace Interdotz\Sdk\Exceptions;

use RuntimeException;

class InterdotzException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?array $context = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}
