<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testJwksCacheKeysMatchThePluginNamingScheme(): void
    {
        [$cacheKey, $refreshKey] = Plugin::jwksCacheKeys('https://team.cloudflareaccess.com');

        self::assertMatchesRegularExpression('/^diesis_wp_jwt_auth_jwks_[0-9a-f]{16}$/', $cacheKey);
        self::assertMatchesRegularExpression('/^diesis_wp_jwt_auth_jwks_refreshed_[0-9a-f]{16}$/', $refreshKey);
    }

    public function testJwksCacheKeysIgnoreTrailingSlash(): void
    {
        self::assertSame(
            Plugin::jwksCacheKeys('https://team.cloudflareaccess.com'),
            Plugin::jwksCacheKeys('https://team.cloudflareaccess.com/'),
        );
    }

    public function testJwksCacheKeysDifferPerIssuer(): void
    {
        [$cacheA] = Plugin::jwksCacheKeys('https://team-a.cloudflareaccess.com');
        [$cacheB] = Plugin::jwksCacheKeys('https://team-b.cloudflareaccess.com');

        self::assertNotSame($cacheA, $cacheB);
    }
}
