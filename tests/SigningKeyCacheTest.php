<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use Diesis\JwtAuth\SigningKeyCache;
use Diesis\JwtAuth\SigningKeysUnavailable;
use PHPUnit\Framework\TestCase;

final class SigningKeyCacheTest extends TestCase
{
    private const ISSUER = 'https://team.cloudflareaccess.com';
    private const CERTS_URL = self::ISSUER . '/cdn-cgi/access/certs';
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

    public function testColdCacheFetchesFromIssuerThenServesFromCache(): void
    {
        $this->responses = [self::KEY_SET_A];
        $cache = $this->cache();

        self::assertSame(self::KEY_SET_A, $cache->keys(false));
        self::assertSame(self::KEY_SET_A, $cache->keys(false));
        self::assertSame([self::CERTS_URL], $this->fetched);
    }

    public function testKeySetIsCachedForTwelveHours(): void
    {
        $this->responses = [self::KEY_SET_A];

        $this->cache()->keys(false);

        self::assertContains(12 * 3600, $this->store->ttls);
    }

    public function testForcedRefreshFetchesWhenLastFetchIsOlderThanFiveMinutes(): void
    {
        $this->primeAnHourAgo(self::KEY_SET_A);
        $this->responses = [self::KEY_SET_B];

        self::assertSame(self::KEY_SET_B, $this->cache()->keys(true));
        self::assertCount(1, $this->fetched);
    }

    public function testForcedRefreshIsThrottledWithinFiveMinutesOfLastFetch(): void
    {
        $this->responses = [self::KEY_SET_A, self::KEY_SET_B];
        $cache = $this->cache();
        $cache->keys(false);
        $this->now += 299;

        self::assertSame(self::KEY_SET_A, $cache->keys(true));
        self::assertCount(1, $this->fetched);
    }

    public function testServesStaleKeysWhenFetchFails(): void
    {
        $this->primeAnHourAgo(self::KEY_SET_A);
        $this->responses = [null];

        self::assertSame(self::KEY_SET_A, $this->cache()->keys(true));
        self::assertCount(1, $this->fetched);
    }

    public function testServesStaleKeysWhenBodyIsMalformed(): void
    {
        $this->primeAnHourAgo(self::KEY_SET_A);
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

        $this->now += 3600;

        self::assertSame(self::KEY_SET_A, $cache->keys(false));
        self::assertCount(2, $this->fetched);
    }

    public function testColdCacheWithUnreachableIssuerFetchesOnceThenGivesUp(): void
    {
        $this->responses = [null, self::KEY_SET_A];
        $cache = $this->cache();

        try {
            $cache->keys(false);
            self::fail('Expected keys to be unavailable.');
        } catch (SigningKeysUnavailable) {
        }

        try {
            $cache->keys(true);
            self::fail('Expected the forced refresh to be throttled.');
        } catch (SigningKeysUnavailable) {
        }

        self::assertCount(1, $this->fetched);
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
        $this->primeAnHourAgo(self::KEY_SET_A);

        self::assertSame(self::KEY_SET_A, $this->cache(self::ISSUER . '/')->keys(false));
        self::assertSame([], $this->fetched);
    }

    public function testDifferentIssuersDoNotShareTheCache(): void
    {
        $this->primeAnHourAgo(self::KEY_SET_A);
        $this->responses = [self::KEY_SET_B];

        self::assertSame(self::KEY_SET_B, $this->cache('https://other.cloudflareaccess.com')->keys(false));
        self::assertCount(1, $this->fetched);
    }

    /**
     * Fill the cache through the interface as if a request an hour ago had done
     * it. The priming fetch is not counted against the test.
     *
     * @param array<string, mixed> $keySet
     */
    private function primeAnHourAgo(array $keySet): void
    {
        $this->now -= 3600;
        $this->responses = [$keySet];
        $this->cache()->keys(false);
        $this->now += 3600;
        $this->fetched = [];
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
