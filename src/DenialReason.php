<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * Every way a request to a protected path can be denied. The string value is
 * what reaches the debug log.
 */
enum DenialReason: string
{
    case TokenMissing = 'token_missing';
    case InvalidAlgorithm = 'invalid_algorithm';
    case InvalidToken = 'invalid_token';
    case KeysUnavailable = 'keys_unavailable';
    case IssuerMismatch = 'issuer_mismatch';
    case AudienceMismatch = 'audience_mismatch';
    case ExpirationMissing = 'expiration_missing';
    case EmailMissing = 'email_missing';
    case EmailNotAllowed = 'email_not_allowed';
}
