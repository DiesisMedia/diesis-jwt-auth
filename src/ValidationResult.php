<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

final class ValidationResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
    ) {
    }

    public static function allow(): self
    {
        return new self(true, 'ok');
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
