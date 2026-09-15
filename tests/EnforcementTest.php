<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use Diesis\JwtAuth\DenialReason;
use Diesis\JwtAuth\Enforcement;
use Diesis\JwtAuth\Settings;
use PHPUnit\Framework\TestCase;

final class EnforcementTest extends TestCase
{
    private const ISSUER = 'https://team.cloudflareaccess.com';
    private const AUDIENCE = 'expected-audience';

    private TestSigningKey $key;

    protected function setUp(): void
    {
        $this->key = new TestSigningKey();
    }

    public function testDisabledEnforcementLetsProtectedRequestsThrough(): void
    {
        $enforcement = $this->enforcement(['enabled' => false]);

        self::assertNull($enforcement->decide('/wp-admin/plugins.php', ''));
    }

    public function testBadStoredIssuerDisablesEnforcementWithoutTouchingKeys(): void
    {
        $settings = Settings::parse(['enabled' => true, 'issuer' => 'https://attacker.example', 'audience' => self::AUDIENCE]);
        $enforcement = new Enforcement($settings, static function (bool $forceRefresh): array {
            self::fail('The key provider must not be called when enforcement is disabled.');
        });

        self::assertNull($enforcement->decide('/wp-admin/plugins.php', 'not.a.jwt'));
    }

    public function testPublicPathNeedsNoToken(): void
    {
        self::assertNull($this->enforcement()->decide('/news/', ''));
    }

    public function testProtectedPathWithoutTokenIsDenied(): void
    {
        self::assertSame(DenialReason::TokenMissing, $this->enforcement()->decide('/wp-admin/plugins.php', ''));
    }

    public function testExcludedPathInsideProtectedPrefixNeedsNoToken(): void
    {
        $enforcement = $this->enforcement([
            'protected_paths' => ['/wp-admin/*'],
            'excluded_paths' => ['/wp-admin/admin-ajax.php'],
        ]);

        self::assertNull($enforcement->decide('/wp-admin/admin-ajax.php?action=public', ''));
        self::assertSame(DenialReason::TokenMissing, $enforcement->decide('/wp-admin/plugins.php', ''));
    }

    public function testProtectedPathWithInvalidTokenIsDenied(): void
    {
        self::assertSame(DenialReason::InvalidAlgorithm, $this->enforcement()->decide('/wp-login.php', 'not.a.jwt'));
    }

    public function testProtectedPathWithValidTokenIsAllowed(): void
    {
        $token = $this->key->sign([
            'iss' => self::ISSUER,
            'aud' => [self::AUDIENCE],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertNull($this->enforcement()->decide('/wp-login.php?redirect_to=%2Fwp-admin%2F', $token));
    }

    public function testValidTokenForAnotherAudienceIsDenied(): void
    {
        $token = $this->key->sign([
            'iss' => self::ISSUER,
            'aud' => ['another-app'],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertSame(DenialReason::AudienceMismatch, $this->enforcement()->decide('/wp-login.php', $token));
    }

    public function testValidTokenForEmailOutsideAllowlistIsDenied(): void
    {
        $token = $this->key->sign([
            'iss' => self::ISSUER,
            'aud' => [self::AUDIENCE],
            'iat' => time() - 5,
            'exp' => time() + 300,
            'email' => 'stranger@example.com',
        ]);

        self::assertSame(
            DenialReason::EmailNotAllowed,
            $this->enforcement(['allowed_emails' => ['admin@example.com']])->decide('/wp-login.php', $token),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function enforcement(array $overrides = []): Enforcement
    {
        $settings = Settings::parse($overrides + [
            'enabled' => true,
            'issuer' => self::ISSUER,
            'audience' => self::AUDIENCE,
        ]);

        return new Enforcement($settings, fn (bool $forceRefresh): array => $this->key->jwks);
    }
}
