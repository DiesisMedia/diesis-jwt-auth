<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        $this->settings = new Settings();
    }

    public function testAcceptsCloudflareIssuerAndEnablesEnforcement(): void
    {
        $result = $this->settings->sanitize([
            'enabled' => '1',
            'issuer' => 'https://team.cloudflareaccess.com/',
            'audience' => '  audience-tag  ',
        ]);

        self::assertTrue($result['enabled']);
        self::assertSame('https://team.cloudflareaccess.com', $result['issuer']);
        self::assertSame('audience-tag', $result['audience']);
    }

    public function testRejectsNonCloudflareIssuerAndDisablesEnforcement(): void
    {
        $result = $this->settings->sanitize([
            'enabled' => '1',
            'issuer' => 'https://attacker.example',
            'audience' => 'audience-tag',
        ]);

        self::assertSame('', $result['issuer']);
        self::assertFalse($result['enabled']);
    }

    public function testRejectsIssuerWithPath(): void
    {
        $result = $this->settings->sanitize([
            'issuer' => 'https://team.cloudflareaccess.com/extra',
        ]);

        self::assertSame('', $result['issuer']);
    }

    public function testEnforcementNeedsIssuerAndAudience(): void
    {
        $result = $this->settings->sanitize([
            'enabled' => '1',
            'issuer' => 'https://team.cloudflareaccess.com',
            'audience' => '',
        ]);

        self::assertFalse($result['enabled']);
    }

    public function testEnabledAcceptsBooleanTrueForProgrammaticUpdates(): void
    {
        $result = $this->settings->sanitize([
            'enabled' => true,
            'issuer' => 'https://team.cloudflareaccess.com',
            'audience' => 'audience-tag',
        ]);

        self::assertTrue($result['enabled']);
    }

    public function testEmailsAreLowercasedDeduplicatedAndFiltered(): void
    {
        $result = $this->settings->sanitize([
            'allowed_emails' => "Admin@Example.com\nadmin@example.com\nnot-an-email\neditor@example.com",
        ]);

        self::assertSame(['admin@example.com', 'editor@example.com'], $result['allowed_emails']);
    }

    public function testPathRulesRejectInvalidEntries(): void
    {
        $result = $this->settings->sanitize([
            'protected_paths' => "/wp-admin\nno-leading-slash\n/a/*/b\n/two**\n/valid/*",
        ]);

        self::assertSame(['/wp-admin', '/valid/*'], $result['protected_paths']);
    }

    public function testProtectedPathsFallBackToDefaultsWhenEmpty(): void
    {
        $result = $this->settings->sanitize([
            'protected_paths' => '',
        ]);

        self::assertSame(['/wp-login.php*', '/wp-admin', '/wp-admin/*'], $result['protected_paths']);
    }

    public function testExcludedPathsStayEmptyWhenEmpty(): void
    {
        $result = $this->settings->sanitize([
            'excluded_paths' => '',
        ]);

        self::assertSame([], $result['excluded_paths']);
    }
}
