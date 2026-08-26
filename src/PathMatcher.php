<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

final class PathMatcher
{
    /**
     * @param list<string> $protectedPaths
     * @param list<string> $excludedPaths
     */
    public function __construct(
        private readonly array $protectedPaths,
        private readonly array $excludedPaths = [],
    ) {
    }

    public function protects(string $requestUri): bool
    {
        $pathParts = preg_split('/[?#]/', $requestUri, 2);
        $path = is_array($pathParts) ? $pathParts[0] : '/';
        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path === '') {
            $path = '/';
        }

        foreach ($this->excludedPaths as $pattern) {
            if ($this->matches($path, $pattern)) {
                return false;
            }
        }

        foreach ($this->protectedPaths as $pattern) {
            if ($this->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $path, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if (! str_ends_with($pattern, '*')) {
            return hash_equals($pattern, $path);
        }

        $prefix = substr($pattern, 0, -1);

        return str_starts_with($path, $prefix);
    }
}
