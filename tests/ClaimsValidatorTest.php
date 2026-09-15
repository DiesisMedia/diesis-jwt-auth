<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use Diesis\JwtAuth\ClaimsValidator;
use Diesis\JwtAuth\DenialReason;
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

        self::assertNull($result);
    }

    public function testRejectsWrongIssuer(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://attacker.example',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertSame(DenialReason::IssuerMismatch, $result);
    }

    public function testRejectsWrongAudience(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['another-audience'],
            'exp' => time() + 300,
            'email' => 'admin@example.com',
        ]);

        self::assertSame(DenialReason::AudienceMismatch, $result);
    }

    public function testRejectsEmailOutsideAllowlist(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'exp' => time() + 300,
            'email' => 'other@example.com',
        ]);

        self::assertSame(DenialReason::EmailNotAllowed, $result);
    }

    public function testRejectsTokenWithoutExpiration(): void
    {
        $result = $this->validator->validate((object) [
            'iss' => 'https://team.cloudflareaccess.com',
            'aud' => ['expected-audience'],
            'email' => 'admin@example.com',
        ]);

        self::assertSame(DenialReason::ExpirationMissing, $result);
    }
}
