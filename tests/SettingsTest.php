<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\Settings;
use Diesis\WpJwtAuth\SettingsProblem;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testAcceptsCloudflareIssuerAndEnablesEnforcement(): void
    {
        $result = Settings::parse([
            'enabled' => '1',
            'issuer' => 'https://team.cloudflareaccess.com/',
            'audience' => '  audience-tag  ',
        ])->toArray();

        self::assertTrue($result['enabled']);
        self::assertSame('https://team.cloudflareaccess.com', $result['issuer']);
        self::assertSame('audience-tag', $result['audience']);
    }

    public function testRejectsNonCloudflareIssuerAndDisablesEnforcement(): void
    {
        $result = Settings::parse([
            'enabled' => '1',
            'issuer' => 'https://attacker.example',
            'audience' => 'audience-tag',
        ])->toArray();

        self::assertSame('', $result['issuer']);
        self::assertFalse($result['enabled']);
    }

    public function testProblemsNameWhatDisabledEnforcement(): void
    {
        $rejectedIssuer = Settings::parse(['enabled' => '1', 'issuer' => 'https://attacker.example', 'audience' => 'a']);
        $missingAudience = Settings::parse(['enabled' => '1', 'issuer' => 'https://team.cloudflareaccess.com']);
        $complete = Settings::parse(['enabled' => '1', 'issuer' => 'https://team.cloudflareaccess.com', 'audience' => 'a']);

        self::assertSame([SettingsProblem::InvalidIssuer], $rejectedIssuer->problems);
        self::assertSame([SettingsProblem::MissingConfiguration], $missingAudience->problems);
        self::assertSame([], $complete->problems);
    }

    public function testRejectsIssuerWithPath(): void
    {
        $result = Settings::parse([
            'issuer' => 'https://team.cloudflareaccess.com/extra',
        ])->toArray();

        self::assertSame('', $result['issuer']);
    }

    public function testEnforcementNeedsIssuerAndAudience(): void
    {
        $result = Settings::parse([
            'enabled' => '1',
            'issuer' => 'https://team.cloudflareaccess.com',
            'audience' => '',
        ])->toArray();

        self::assertFalse($result['enabled']);
    }

    public function testEnabledAcceptsBooleanTrueForProgrammaticUpdates(): void
    {
        $result = Settings::parse([
            'enabled' => true,
            'issuer' => 'https://team.cloudflareaccess.com',
            'audience' => 'audience-tag',
        ])->toArray();

        self::assertTrue($result['enabled']);
    }

    public function testEmailsAreLowercasedDeduplicatedAndFiltered(): void
    {
        $result = Settings::parse([
            'allowed_emails' => "Admin@Example.com\nadmin@example.com\nnot-an-email\neditor@example.com",
        ])->toArray();

        self::assertSame(['admin@example.com', 'editor@example.com'], $result['allowed_emails']);
    }

    public function testPathRulesRejectInvalidEntries(): void
    {
        $result = Settings::parse([
            'protected_paths' => "/wp-admin\nno-leading-slash\n/a/*/b\n/two**\n/valid/*",
        ])->toArray();

        self::assertSame(['/wp-admin', '/valid/*'], $result['protected_paths']);
    }

    public function testProtectedPathsFallBackToDefaultsWhenEmpty(): void
    {
        $result = Settings::parse([
            'protected_paths' => '',
        ])->toArray();

        self::assertSame(['/wp-login.php*', '/wp-admin', '/wp-admin/*'], $result['protected_paths']);
    }

    public function testExcludedPathsStayEmptyWhenEmpty(): void
    {
        $result = Settings::parse([
            'excluded_paths' => '',
        ])->toArray();

        self::assertSame([], $result['excluded_paths']);
    }

    public function testStoredRowIsReadWithNormalizedValues(): void
    {
        $settings = Settings::parse([
            'enabled' => true,
            'issuer' => 'https://team.cloudflareaccess.com/',
            'audience' => ' audience-tag ',
            'allowed_emails' => ['Admin@Example.com'],
            'protected_paths' => ['/wp-admin/*'],
            'excluded_paths' => ['/wp-admin/admin-ajax.php'],
        ]);

        self::assertTrue($settings->enabled);
        self::assertSame('https://team.cloudflareaccess.com', $settings->issuer);
        self::assertSame('audience-tag', $settings->audience);
        self::assertSame(['admin@example.com'], $settings->allowedEmails);
        self::assertSame(['/wp-admin/*'], $settings->protectedPaths);
        self::assertSame(['/wp-admin/admin-ajax.php'], $settings->excludedPaths);
    }

    public function testStoredLegacyStringFlagEnablesEnforcement(): void
    {
        $settings = Settings::parse([
            'enabled' => '1',
            'issuer' => 'https://team.cloudflareaccess.com',
            'audience' => 'audience-tag',
        ]);

        self::assertTrue($settings->enabled);
    }

    public function testStoredNonCloudflareIssuerDisablesEnforcement(): void
    {
        $settings = Settings::parse([
            'enabled' => true,
            'issuer' => 'https://attacker.example',
            'audience' => 'audience-tag',
        ]);

        self::assertFalse($settings->enabled);
        self::assertSame('', $settings->issuer);
    }

    public function testStoredRowWithoutAudienceDisablesEnforcement(): void
    {
        $settings = Settings::parse([
            'enabled' => true,
            'issuer' => 'https://team.cloudflareaccess.com',
        ]);

        self::assertFalse($settings->enabled);
    }

    public function testMissingOptionYieldsDefaults(): void
    {
        $settings = Settings::parse(false);

        self::assertFalse($settings->enabled);
        self::assertSame('', $settings->issuer);
        self::assertSame(['/wp-login.php*', '/wp-admin', '/wp-admin/*'], $settings->protectedPaths);
        self::assertSame([], $settings->excludedPaths);
    }

    public function testStoredEmptyProtectedPathsFallBackToDefaults(): void
    {
        $settings = Settings::parse(['protected_paths' => []]);

        self::assertSame(['/wp-login.php*', '/wp-admin', '/wp-admin/*'], $settings->protectedPaths);
    }

    public function testStoredArrayFormRoundTrips(): void
    {
        $stored = Settings::parse([
            'enabled' => '1',
            'issuer' => 'https://team.cloudflareaccess.com',
            'audience' => 'audience-tag',
            'allowed_emails' => "admin@example.com",
        ])->toArray();

        self::assertEquals($stored, Settings::parse($stored)->toArray());
    }
}
