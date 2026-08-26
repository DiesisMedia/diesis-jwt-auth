<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\AccessTokenVerifier;
use Diesis\WpJwtAuth\ClaimsValidator;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class AccessTokenVerifierTest extends TestCase
{
    private string $privateKey;

    /** @var array<string, mixed> */
    private array $jwks;

    protected function setUp(): void
    {
        $key = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($key);
        $privateKey = '';
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsArray($details['rsa']);

        $this->jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'test-key',
                'n' => self::base64Url($details['rsa']['n']),
                'e' => self::base64Url($details['rsa']['e']),
            ]],
        ];
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
        ], $this->privateKey, 'RS256', 'test-key');

        self::assertTrue($verifier->verify($token)->allowed);
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
        ], $this->privateKey, 'RS256', 'test-key');
        $segments = explode('.', $token);
        $segments[1] = self::base64Url('{"email":"attacker@example.com"}');

        self::assertFalse($verifier->verify(implode('.', $segments))->allowed);
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

        self::assertFalse($result->allowed);
        self::assertSame('invalid_algorithm', $result->reason);
    }

    public function testRejectsExpiredToken(): void
    {
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 600,
            'exp' => time() - 300,
            'email' => 'admin@example.com',
        ], $this->privateKey, 'RS256', 'test-key');

        self::assertFalse($this->verifier()->verify($token)->allowed);
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

                return $forceRefresh ? $this->jwks : ['keys' => []];
            },
        );
        $token = JWT::encode([
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ], $this->privateKey, 'RS256', 'test-key');

        self::assertTrue($verifier->verify($token)->allowed);
        self::assertSame([false, true], $refreshAttempts);
    }

    private function verifier(): AccessTokenVerifier
    {
        return new AccessTokenVerifier(
            new ClaimsValidator(
                'https://team.cloudflareaccess.com',
                'expected-audience',
                ['admin@example.com'],
            ),
            fn (bool $forceRefresh): array => $this->jwks,
        );
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
