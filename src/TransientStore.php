<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

/**
 * Seam for the network-wide transient storage the signing key cache uses.
 * WordPress site transients fill it in production, memory fills it in tests.
 */
interface TransientStore
{
    /** Returns null when the name is missing or expired. */
    public function get(string $name): mixed;

    public function set(string $name, mixed $value, int $ttl): void;

    public function delete(string $name): void;
}
