<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use Diesis\JwtAuth\Vendor\Firebase\JWT\JWT;
use PHPUnit\Framework\Assert;

/**
 * A fresh RSA key pair per test, exposed as the JWKS Cloudflare would publish
 * and as a signer for Access JWTs.
 */
final class TestSigningKey
{
    public const KID = 'test-key';

    public readonly string $privateKey;

    /** @var array<string, mixed> */
    public readonly array $jwks;

    public function __construct()
    {
        $key = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        Assert::assertNotFalse($key);
        $privateKey = '';
        Assert::assertTrue(openssl_pkey_export($key, $privateKey));
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        Assert::assertIsArray($details);
        Assert::assertIsArray($details['rsa']);

        $this->jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => self::KID,
                'n' => self::base64Url($details['rsa']['n']),
                'e' => self::base64Url($details['rsa']['e']),
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function sign(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', self::KID);
    }

    public static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
