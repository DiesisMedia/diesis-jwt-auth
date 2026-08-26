<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use JsonException;
use Throwable;

final class AccessTokenVerifier
{
    private readonly Closure $jwksProvider;

    /**
     * @param callable(bool): array<string, mixed> $jwksProvider
     */
    public function __construct(
        private readonly ClaimsValidator $claimsValidator,
        callable $jwksProvider,
        private readonly int $leeway = 60,
    ) {
        $this->jwksProvider = Closure::fromCallable($jwksProvider);
    }

    public function verify(string $token): ValidationResult
    {
        if (! $this->usesRs256($token)) {
            return ValidationResult::deny('invalid_algorithm');
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->leeway;

        try {
            foreach ([false, true] as $refreshKeys) {
                try {
                    $jwks = ($this->jwksProvider)($refreshKeys);
                    $keys = JWK::parseKeySet($jwks, 'RS256');
                    $claims = JWT::decode($token, $keys);

                    return $this->claimsValidator->validate($claims);
                } catch (Throwable $exception) {
                    if ($refreshKeys) {
                        return ValidationResult::deny('invalid_token');
                    }
                }
            }
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        return ValidationResult::deny('invalid_token');
    }

    private function usesRs256(string $token): bool
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return false;
        }

        try {
            $header = json_decode(JWT::urlsafeB64Decode($segments[0]), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($header) && ($header['alg'] ?? null) === 'RS256';
    }
}
