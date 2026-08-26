<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\ClaimsValidator;
use PHPUnit\Framework\TestCase;

final class ClaimsValidatorTest extends TestCase
{
    private ClaimsValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ClaimsValidator(
            'https://team.cloudflareaccess.com',
            'expected-audience',
            ['admin@example.com'],
        );
    }

    public function testAcceptsExpectedClaims(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'ADMIN@example.com',
        ]);

        self::assertTrue($result->allowed);
    }

    public function testRejectsWrongIssuer(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://attacker.example',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertFalse($result->allowed);
        self::assertSame('issuer_mismatch', $result->reason);
    }

    public function testRejectsWrongAudience(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['another-audience'],
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertFalse($result->allowed);
        self::assertSame('audience_mismatch', $result->reason);
    }

    public function testRejectsEmailOutsideAllowlist(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'other@example.com',
        ]);

        self::assertFalse($result->allowed);
        self::assertSame('email_not_allowed', $result->reason);
    }

    public function testRejectsTokenWithoutExpiration(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'email' => 'admin@example.com',
        ]);

        self::assertFalse($result->allowed);
        self::assertSame('expiration_missing', $result->reason);
    }
}
