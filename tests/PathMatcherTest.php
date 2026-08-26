<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\PathMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function requests(): iterable
    {
        yield 'login' => ['/wp-login.php', true];
        yield 'login query' => ['/wp-login.php?redirect_to=%2Fwp-admin%2F', true];
        yield 'login trailing slash' => ['/wp-login.php/', true];
        yield 'encoded login' => ['/wp-login%2Ephp', true];
        yield 'admin exact' => ['/wp-admin', true];
        yield 'admin directory' => ['/wp-admin/', true];
        yield 'duplicate slash' => ['//wp-admin//plugins.php', true];
        yield 'admin child' => ['/wp-admin/plugins.php', true];
        yield 'similar prefix' => ['/wp-administrator', false];
        yield 'public page' => ['/news/', false];
    }

    #[DataProvider('requests')]
    public function testMatchesProtectedPaths(string $requestUri, bool $expected): void
    {
        $matcher = new PathMatcher(['/wp-login.php*', '/wp-admin', '/wp-admin/*']);

        self::assertSame($expected, $matcher->protects($requestUri));
    }

    public function testExclusionTakesPrecedence(): void
    {
        $matcher = new PathMatcher(
            ['/wp-admin/*'],
            ['/wp-admin/admin-ajax.php'],
        );

        self::assertFalse($matcher->protects('/wp-admin/admin-ajax.php?action=public'));
        self::assertTrue($matcher->protects('/wp-admin/plugins.php'));
    }
}
