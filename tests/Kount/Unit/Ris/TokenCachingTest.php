<?php

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Test subclass of Kount_Ris_Request_Inquiry that replaces the network token
 * fetch with a canned, deterministic token and counts how many times the
 * server fetch is invoked. This lets the token-caching logic be exercised
 * entirely offline.
 */
class FakeFetchInquiry extends Kount_Ris_Request_Inquiry
{
    /**
     * Number of times fetchAccessTokenFromServer() has been called.
     * @var int
     */
    public int $fetchCount = 0;

    /**
     * Return a canned token instead of making a network call.
     *
     * @return array|null
     */
    protected function fetchAccessTokenFromServer(): ?array
    {
        $this->fetchCount++;
        return array(
            'access_token' => 'tok123',
            'token_type' => 'bearer',
            'expires_in' => 300,
            'expires_at' => (new DateTime())->getTimestamp() + 240,
        );
    }

    /**
     * Public test hook to drive token refresh offline.
     */
    public function callRefresh(): void
    {
        $this->refreshPaymentsFraudAccessToken();
    }

    /**
     * Public test hook to simulate a fresh process / expired in-memory token.
     */
    public function clearInMemoryToken(): void
    {
        $this->accessToken = array();
    }
}

/**
 * Offline tests verifying the Payments Fraud auth-token caching logic without
 * touching the network.
 */
class TokenCachingTest extends TestCase
{
    /**
     * Build a minimal in-memory PSR-16 cache that can be shared between
     * request instances.
     */
    private function makeSharedPsr16(): CacheInterface
    {
        return new class implements CacheInterface {
            private array $store = array();

            public function get(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, $this->store) ? $this->store[$key] : $default;
            }

            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
            {
                $this->store[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->store = array();
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                $out = array();
                foreach ($keys as $key) {
                    $out[$key] = $this->get($key, $default);
                }
                return $out;
            }

            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
            {
                foreach ($values as $key => $value) {
                    $this->set($key, $value, $ttl);
                }
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                foreach ($keys as $key) {
                    $this->delete($key);
                }
                return true;
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->store);
            }
        };
    }

    /**
     * Build settings with migration mode enabled and dummy PF_* values so the
     * Inquiry constructor takes the Payments Fraud path. setPayment is never
     * called, so the dummy CONFIG_KEY is never decoded.
     */
    private function makeSettings(): Kount_Ris_ArraySettings
    {
        // 72-char dummy base85 config key (never decoded because setPayment is
        // not called in these tests).
        $configKey = str_repeat('A', 72);

        return new Kount_Ris_ArraySettings(array(
            'CONFIG_KEY' => $configKey,
            'MERCHANT_ID' => '999666',
            'URL' => 'https://risk.test.kount.net',
            'VERS' => '0720',
            'CONNECT_TIMEOUT' => '30',
            'SDK' => 'PHP',
            'SDK_VERSION' => 'Sdk-Ris-PHP-0.0.0',
            'MIGRATION_MODE_ENABLED' => true,
            'PF_CLIENT_ID' => 'dummy-client-id',
            'PF_AUTH_ENDPOINT' => 'https://auth.example.test/oauth2/token',
            'PF_API_ENDPOINT' => 'https://api.example.test/ris',
            'PF_API_KEY' => 'dummy-pf-api-key',
        ));
    }

    public function testSecondInstanceReadsTokenFromSharedCache(): void
    {
        $cache = $this->makeSharedPsr16();
        $key = 'kount_ris_pf_token_' . hash(
            'sha256',
            'https://auth.example.test/oauth2/token' . '|' . 'dummy-client-id'
        );

        // First instance: constructor fetches once (no cache yet). Then we
        // inject the cache, clear the in-memory token and refresh so it writes
        // the fetched token to the shared cache.
        $first = new FakeFetchInquiry($this->makeSettings());
        $this->assertSame(1, $first->fetchCount, 'constructor should fetch exactly once');

        $first->setTokenCache($cache);
        $first->clearInMemoryToken();
        $first->callRefresh();

        // The refresh re-fetched (in-memory token was cleared, cache was empty)
        // and then wrote the result to the shared cache.
        $this->assertSame(2, $first->fetchCount);
        $this->assertTrue($cache->has($key), 'token should be persisted in shared cache');

        // Second instance: shares the cache. Its constructor fetched once with
        // no cache. We then inject the SAME cache, clear the in-memory token to
        // simulate an expired/cold in-memory state, and refresh. It must read
        // from the cache and NOT call fetchAccessTokenFromServer again.
        $second = new FakeFetchInquiry($this->makeSettings());
        $this->assertSame(1, $second->fetchCount, 'constructor should fetch exactly once');

        $second->setTokenCache($cache);
        $second->clearInMemoryToken();
        $second->callRefresh();

        $this->assertSame(
            1,
            $second->fetchCount,
            'second instance must read from cache and not re-fetch'
        );
    }

    public function testInMemoryTokenShortCircuitsBeforeCache(): void
    {
        $first = new FakeFetchInquiry($this->makeSettings());
        $this->assertSame(1, $first->fetchCount);

        // A valid in-memory token is still present from construction; a refresh
        // must be a no-op and never re-fetch.
        $first->callRefresh();
        $this->assertSame(1, $first->fetchCount);
    }
}
