<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth\Tests;

use Diesis\WpJwtAuth\SigningKeyCache;
use Diesis\WpJwtAuth\SigningKeysUnavailable;
use PHPUnit\Framework\TestCase;

final class SigningKeyCacheTest extends TestCase
{
    private const ISSUER = 'https://team.cloudflareaccess.com';
    private const KEY_SET_A = ['keys' => [['kid' => 'a', 'kty' => 'RSA']]];
    private const KEY_SET_B = ['keys' => [['kid' => 'b', 'kty' => 'RSA']]];

    private InMemoryTransientStore $store;

    /** @var list<string> URLs the fetch dependency was called with */
    private array $fetched = [];

    /** @var list<mixed> scripted fetch responses, consumed in order */
    private array $responses = [];

    private int $now = 1_700_000_000;

    protected function setUp(): void
    {
        $this->store = new InMemoryTransientStore();
    }

    public function testServesCachedKeysWithoutFetching(): void
    {
        $this->prime(self::KEY_SET_A);

        self::assertSame(self::KEY_SET_A, $this->cache()->keys(false));
        self::assertSame([], $this->fetched);
    }

    public function testColdCacheFetchesFromIssuerAndCachesForTwelveHours(): void
    {
        $this->responses = [self::KEY_SET_A];
        $cache = $this->cache();

        self::assertSame(self::KEY_SET_A, $cache->keys(false));
        self::assertSame([self::ISSUER . '/cdn-cgi/access/certs'], $this->fetched);
        self::assertSame(self::KEY_SET_A, $cache->keys(false));
        self::assertCount(1, $this->fetched);
        self::assertContains(12 * 3600, $this->store->ttls);
    }

    public function testForcedRefreshFetchesWhenLastFetchIsOlderThanFiveMinutes(): void
    {
        $this->prime(self::KEY_SET_A, fetchedAt: $this->now - 301);
        $this->responses = [self::KEY_SET_B];

        self::assertSame(self::KEY_SET_B, $this->cache()->keys(true));
        self::assertCount(1, $this->fetched);
    }

    public function testForcedRefreshIsThrottledWithinFiveMinutesOfLastFetch(): void
    {
        $this->prime(self::KEY_SET_A, fetchedAt: $this->now - 60);
        $this->responses = [self::KEY_SET_B];

        self::assertSame(self::KEY_SET_A, $this->cache()->keys(true));
        self::assertSame([], $this->fetched);
    }

    public function testColdFillCountsAsFetchForTheThrottle(): void
    {
        $this->responses = [self::KEY_SET_A, self::KEY_SET_B];
        $cache = $this->cache();

        self::assertSame(self::KEY_SET_A, $cache->keys(false));
        self::assertSame(self::KEY_SET_A, $cache->keys(true));
        self::assertCount(1, $this->fetched);
    }

    public function testServesStaleKeysWhenFetchFails(): void
    {
        $this->prime(self::KEY_SET_A, fetchedAt: $this->now - 3600);
        $this->responses = [null];

        self::assertSame(self::KEY_SET_A, $this->cache()->keys(true));
        self::assertCount(1, $this->fetched);
    }

    public function testServesStaleKeysWhenBodyIsMalformed(): void
    {
        $this->prime(self::KEY_SET_A, fetchedAt: $this->now - 3600);
        $this->responses = [['keys' => []]];

        self::assertSame(self::KEY_SET_A, $this->cache()->keys(true));
    }

    public function testMalformedBodyIsNeverCached(): void
    {
        $this->responses = [['not' => 'a key set'], self::KEY_SET_A];
        $cache = $this->cache();

        try {
            $cache->keys(false);
            self::fail('Expected keys to be unavailable.');
        } catch (SigningKeysUnavailable) {
        }

        self::assertSame([], $this->store->values['diesis_wp_jwt_auth_jwks_' . $this->hash()] ?? []);
    }

    public function testColdCacheWithUnreachableIssuerFetchesOnceThenGivesUp(): void
    {
        $this->responses = [null, self::KEY_SET_A];
        $cache = $this->cache();

        $this->expectException(SigningKeysUnavailable::class);

        try {
            $cache->keys(false);
        } catch (SigningKeysUnavailable) {
        }

        try {
            $cache->keys(true);
        } finally {
            self::assertCount(1, $this->fetched);
        }
    }

    public function testPurgeRemovesEverythingTheCacheStored(): void
    {
        $this->responses = [self::KEY_SET_A];
        $cache = $this->cache();
        $cache->keys(false);
        self::assertNotSame([], $this->store->values);

        $cache->purge();

        self::assertSame([], $this->store->values);
    }

    public function testTrailingSlashOnIssuerSharesTheCache(): void
    {
        $this->prime(self::KEY_SET_A);

        self::assertSame(self::KEY_SET_A, $this->cache(self::ISSUER . '/')->keys(false));
        self::assertSame([], $this->fetched);
    }

    public function testDifferentIssuersDoNotShareTheCache(): void
    {
        $this->prime(self::KEY_SET_A);
        $this->responses = [self::KEY_SET_B];

        self::assertSame(self::KEY_SET_B, $this->cache('https://other.cloudflareaccess.com')->keys(false));
    }

    /**
     * @param array<string, mixed> $keySet
     */
    private function prime(array $keySet, ?int $fetchedAt = null): void
    {
        $this->store->set('diesis_wp_jwt_auth_jwks_' . $this->hash(), $keySet, 12 * 3600);
        $this->store->set('diesis_wp_jwt_auth_jwks_refreshed_' . $this->hash(), $fetchedAt ?? $this->now - 3600, 86400);
    }

    private function hash(): string
    {
        return substr(hash('sha256', self::ISSUER), 0, 16);
    }

    private function cache(string $issuer = self::ISSUER): SigningKeyCache
    {
        return new SigningKeyCache(
            $issuer,
            $this->store,
            function (string $url): mixed {
                $this->fetched[] = $url;

                return array_shift($this->responses);
            },
            fn (): int => $this->now,
        );
    }
}
