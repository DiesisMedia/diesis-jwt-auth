<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

final class ClaimsValidator
{
    /**
     * @param list<string> $allowedEmails
     */
    public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly array $allowedEmails = [],
    ) {
    }

    /**
     * @return DenialReason|null null when every claim checks out
     */
    public function validate(object $claims): ?DenialReason
    {
        if (! isset($claims->iss) || ! is_string($claims->iss) || ! hash_equals($this->issuer, rtrim($claims->iss, '/'))) {
            return DenialReason::IssuerMismatch;
        }

        if (! $this->hasExpectedAudience($claims->aud ?? null)) {
            return DenialReason::AudienceMismatch;
        }

        if (! isset($claims->exp) || (! is_int($claims->exp) && ! is_float($claims->exp))) {
            return DenialReason::ExpirationMissing;
        }

        if (! isset($claims->email) || ! is_string($claims->email) || ! is_email($claims->email)) {
            return DenialReason::EmailMissing;
        }

        if ($this->allowedEmails !== [] && ! $this->emailIsAllowed($claims->email)) {
            return DenialReason::EmailNotAllowed;
        }

        return null;
    }

    private function hasExpectedAudience(mixed $audience): bool
    {
        if (is_string($audience)) {
            return hash_equals($this->audience, $audience);
        }

        if (! is_array($audience)) {
            return false;
        }

        foreach ($audience as $candidate) {
            if (is_string($candidate) && hash_equals($this->audience, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function emailIsAllowed(string $email): bool
    {
        $normalizedEmail = strtolower($email);

        foreach ($this->allowedEmails as $allowedEmail) {
            if (hash_equals(strtolower($allowedEmail), $normalizedEmail)) {
                return true;
            }
        }

        return false;
    }
}
