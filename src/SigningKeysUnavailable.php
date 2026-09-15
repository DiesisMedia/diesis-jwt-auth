<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

use RuntimeException;

/**
 * Thrown when no usable Cloudflare Access key set can be produced: nothing is
 * cached and the issuer could not be fetched or returned a malformed body.
 */
final class SigningKeysUnavailable extends RuntimeException
{
}
