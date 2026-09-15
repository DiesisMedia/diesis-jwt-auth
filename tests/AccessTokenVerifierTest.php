<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use Diesis\JwtAuth\AccessTokenVerifier;
use Diesis\JwtAuth\ClaimsValidator;
use Diesis\JwtAuth\DenialReason;
use Diesis\JwtAuth\SigningKeyCache;
use Diesis\JwtAuth\SigningKeysUnavailable;
use Diesis\JwtAuth\Vendor\Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class AccessTokenVerifierTest extends TestCase
{
    private TestSigningKey $key;

    protected function setUp(): void
    {
        $this->key = new TestSigningKey();
    }

    public function testVerifiesSignatureAndClaims(): void
    {
        $verifier = $this->verifier();
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 5,
            'nbf' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ], $this->key->privateKey, 'RS256', 'test-key');

        self::assertNull($verifier->verify($token));
    }

    public function testRejectsTamperedToken(): void
    {
        $verifier = $this->verifier();
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ], $this->key->privateKey, 'RS256', 'test-key');
        $segments = explode('.', $token);
        $segments[1] = TestSigningKey::base64Url('{"email":"attacker@example.com"}');

        self::assertSame(DenialReason::InvalidToken, $verifier->verify(implode('.', $segments)));
    }

    public function testRejectsNonRs256Token(): void
    {
        $verifier = $this->verifier();
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ], str_repeat('x', 32), 'HS256');

        $result = $verifier->verify($token);

        self::assertSame(DenialReason::InvalidAlgorithm, $result);
    }

    public function testRejectsExpiredToken(): void
    {
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 600,
            'exp' => time() - 300,
            'email' => 'admin@example.com',
        ], $this->key->privateKey, 'RS256', 'test-key');

        self::assertSame(DenialReason::InvalidToken, $this->verifier()->verify($token));
    }

    public function testRefreshesKeysOnceWhenCachedSetCannotVerifyToken(): void
    {
        $refreshAttempts = [];
        $verifier = new AccessTokenVerifier(
            new ClaimsValidator(
                'https://team.cloudflareaccess.com',
                'expected-audience',
                ['admin@example.com'],
            ),
            function (bool $forceRefresh) use (&$refreshAttempts): array {
                $refreshAttempts[] = $forceRefresh;

                return $forceRefresh ? $this->key->jwks : ['keys' => []];
            },
        );
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ], $this->key->privateKey, 'RS256', 'test-key');

        self::assertNull($verifier->verify($token));
        self::assertSame([false, true], $refreshAttempts);
    }

    public function testRotatedKeyIsPickedUpThroughTheRealCache(): void
    {
        $store = new InMemoryTransientStore();
        $rotatedOut = new TestSigningKey();
        $fetches = 0;
        $cache = function (int $now) use ($store, $rotatedOut, &$fetches): SigningKeyCache {
            return new SigningKeyCache(
                'https://team.cloudflareaccess.com',
                $store,
                function () use ($rotatedOut, &$fetches): array {
                    $fetches++;

                    return $fetches === 1 ? $rotatedOut->jwks : $this->key->jwks;
                },
                static fn (): int => $now,
            );
        };
        $cache(time() - 3600)->keys(false);
        $verifier = new AccessTokenVerifier(
            new ClaimsValidator('https://team.cloudflareaccess.com', 'expected-audience'),
            $cache(time())->keys(...),
        );
        $token = $this->key->sign([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertNull($verifier->verify($token));
        self::assertSame(2, $fetches);
    }

    public function testDeniesWithItsOwnReasonWhenSigningKeysAreUnavailable(): void
    {
        $attempts = 0;
        $verifier = new AccessTokenVerifier(
            new ClaimsValidator('https://team.cloudflareaccess.com', 'expected-audience'),
            function (bool $forceRefresh) use (&$attempts): array {
                $attempts++;

                throw new SigningKeysUnavailable('offline');
            },
        );
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ], $this->key->privateKey, 'RS256', 'test-key');

        $result = $verifier->verify($token);

        self::assertSame(DenialReason::KeysUnavailable, $result);
        self::assertSame(1, $attempts);
    }

    private function verifier(): AccessTokenVerifier
    {
        return new AccessTokenVerifier(
            new ClaimsValidator(
                'https://team.cloudflareaccess.com',
                'expected-audience',
                ['admin@example.com'],
            ),
            fn (bool $forceRefresh): array => $this->key->jwks,
        );
    }
}
