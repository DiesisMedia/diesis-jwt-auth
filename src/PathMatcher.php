<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

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
        $path = $this->normalize($requestUri);

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

    /**
     * Reduce a request target to the canonical absolute path the origin server
     * resolves it to, so that encoding tricks and dot segments cannot dodge a
     * protected pattern. Web servers resolve "/a/../wp-login.php" to
     * "/wp-login.php" before serving it while passing the raw form on in
     * REQUEST_URI; the matcher has to perform the same reduction.
     */
    private function normalize(string $requestUri): string
    {
        $parts = preg_split('/[?#]/', $requestUri, 2);
        $path = is_array($parts) ? $parts[0] : $requestUri;
        $path = rawurldecode($path);

        // Absolute-form request target (RFC 9112): strip scheme and authority.
        if (preg_match('#^[a-z][a-z0-9+.-]*://[^/]*(/.*)?$#i', $path, $matches) === 1) {
            $path = $matches[1] ?? '';
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = self::removeDotSegments($path);

        if ($path === '' || $path[0] !== '/') {
            return '/';
        }

        return $path;
    }

    /**
     * RFC 3986 section 5.2.4 remove_dot_segments. Preserves trailing slashes so
     * that exact and prefix patterns keep matching as before.
     */
    private static function removeDotSegments(string $path): string
    {
        $output = '';

        while ($path !== '') {
            if (str_starts_with($path, '../')) {
                $path = substr($path, 3);
            } elseif (str_starts_with($path, './')) {
                $path = substr($path, 2);
            } elseif (str_starts_with($path, '/./')) {
                $path = '/' . substr($path, 3);
            } elseif ($path === '/.') {
                $path = '/';
            } elseif (str_starts_with($path, '/../')) {
                $path = '/' . substr($path, 4);
                $output = self::removeLastSegment($output);
            } elseif ($path === '/..') {
                $path = '/';
                $output = self::removeLastSegment($output);
            } elseif ($path === '.' || $path === '..') {
                $path = '';
            } else {
                $slash = strpos($path, '/', 1);

                if ($slash === false) {
                    $output .= $path;
                    $path = '';
                } else {
                    $output .= substr($path, 0, $slash);
                    $path = substr($path, $slash);
                }
            }
        }

        return $output;
    }

    private static function removeLastSegment(string $output): string
    {
        $position = strrpos($output, '/');

        return $position === false ? '' : substr($output, 0, $position);
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
