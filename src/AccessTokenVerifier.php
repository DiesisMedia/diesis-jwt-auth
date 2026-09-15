<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

use Closure;
use Diesis\JwtAuth\Vendor\Firebase\JWT\JWK;
use Diesis\JwtAuth\Vendor\Firebase\JWT\JWT;
use JsonException;
use Throwable;

final class AccessTokenVerifier
{
    private readonly Closure $jwksProvider;

    /**
     * @param callable(bool): array<string, mixed> $jwksProvider receives true when
     *   the cached set could not verify the token; may throw SigningKeysUnavailable
     */
    public function __construct(
        private readonly ClaimsValidator $claimsValidator,
        callable $jwksProvider,
        private readonly int $leeway = 60,
    ) {
        $this->jwksProvider = Closure::fromCallable($jwksProvider);
    }

    /**
     * @return DenialReason|null null when the token is valid for this issuer and audience
     */
    public function verify(string $token): ?DenialReason
    {
        if (! $this->usesRs256($token)) {
            return DenialReason::InvalidAlgorithm;
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->leeway;

        try {
            foreach ([false, true] as $refreshKeys) {
                try {
                    $jwks = ($this->jwksProvider)($refreshKeys);
                } catch (SigningKeysUnavailable) {
                    return DenialReason::KeysUnavailable;
                }

                try {
                    $keys = JWK::parseKeySet($jwks, 'RS256');
                    $claims = JWT::decode($token, $keys);

                    return $this->claimsValidator->validate($claims);
                } catch (Throwable $exception) {
                    if ($refreshKeys) {
                        return DenialReason::InvalidToken;
                    }
                }
            }
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        return DenialReason::InvalidToken;
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
