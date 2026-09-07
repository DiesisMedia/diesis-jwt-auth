<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\TransientStore;

/**
 * Test adapter for the transient seam. Remembers the TTL per name so tests can
 * assert how long a value was meant to live without a clock.
 */
final class InMemoryTransientStore implements TransientStore
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, int> */
    public array $ttls = [];

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, mixed $value, int $ttl): void
    {
        $this->values[$name] = $value;
        $this->ttls[$name] = $ttl;
    }

    public function delete(string $name): void
    {
        unset($this->values[$name], $this->ttls[$name]);
    }
}
