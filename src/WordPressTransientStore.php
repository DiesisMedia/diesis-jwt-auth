<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * Site transients are network-wide and go through the persistent object cache
 * when one is configured, so purging through them also clears Redis or
 * Memcached entries a direct SQL delete would miss.
 */
final class WordPressTransientStore implements TransientStore
{
    public function get(string $name): mixed
    {
        $value = get_site_transient($name);

        return $value === false ? null : $value;
    }

    public function set(string $name, mixed $value, int $ttl): void
    {
        set_site_transient($name, $value, $ttl);
    }

    public function delete(string $name): void
    {
        delete_site_transient($name);
    }
}
