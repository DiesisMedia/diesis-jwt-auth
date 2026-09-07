<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

/**
 * Decides whether a request may reach the origin. Built once per request from
 * the Settings value and a signing key provider; decide() is pure apart from
 * what the key provider does.
 */
final class Enforcement
{
    private readonly PathMatcher $matcher;
    private readonly AccessTokenVerifier $verifier;

    /**
     * @param callable(bool): array<string, mixed> $keys see AccessTokenVerifier
     */
    public function __construct(
        private readonly Settings $settings,
        callable $keys,
    ) {
        $this->matcher = new PathMatcher($settings->protectedPaths, $settings->excludedPaths);
        $this->verifier = new AccessTokenVerifier(
            new ClaimsValidator($settings->issuer, $settings->audience, $settings->allowedEmails),
            $keys,
        );
    }

    /**
     * @param string $requestUri the raw request target as the server received it
     * @param string $token the Cf-Access-Jwt-Assertion header value, empty when absent
     * @return DenialReason|null null when the request may proceed
     */
    public function decide(string $requestUri, string $token): ?DenialReason
    {
        if (! $this->settings->enabled || ! $this->matcher->protects($requestUri)) {
            return null;
        }

        if ($token === '') {
            return DenialReason::TokenMissing;
        }

        return $this->verifier->verify($token);
    }
}
