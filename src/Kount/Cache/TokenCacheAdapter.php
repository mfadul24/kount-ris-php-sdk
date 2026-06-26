<?php

/**
 * TokenCacheAdapter.php file containing Kount_Cache_TokenCacheAdapter class.
 *
 * @package Kount
 * @subpackage Cache
 */

/**
 * Uniform adapter wrapping EITHER a PSR-6 cache item pool
 * (Psr\Cache\CacheItemPoolInterface) OR a PSR-16 simple cache
 * (Psr\SimpleCache\CacheInterface) behind a single, small API used by the
 * SDK to persist the Payments Fraud authentication token across request
 * instances and processes.
 *
 * The concrete cache type is detected via instanceof in the constructor.
 *
 * All cache operations are fail-open: any error raised by the underlying
 * cache (connection failure, serialization error, etc.) is caught and
 * swallowed so that a cache problem can never break authentication. On a
 * read error get() returns null (cache miss) and the SDK falls back to its
 * normal network refresh; on a write error set() simply does nothing.
 *
 * PSR-6 forbids the characters {}()/\@: in cache keys. The caller is
 * responsible for passing an already-safe key. The SDK key generator
 * (Kount_Ris_Request::getTokenCacheKey) uses only hex characters and
 * underscores, so the keys it produces are PSR-6-safe.
 *
 * @package Kount
 * @subpackage Cache
 * @author Kount <custserv@kount.com>
 * @copyright 2025 Kount, Inc. All Rights Reserved.
 */
class Kount_Cache_TokenCacheAdapter
{
    /**
     * The wrapped PSR-6 cache item pool, or null when a PSR-16 cache is used.
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $psr6;

    /**
     * The wrapped PSR-16 simple cache, or null when a PSR-6 pool is used.
     * @var \Psr\SimpleCache\CacheInterface|null
     */
    private $psr16;

    /**
     * Constructor.
     *
     * @param \Psr\Cache\CacheItemPoolInterface|\Psr\SimpleCache\CacheInterface $cache
     *        A PSR-6 cache item pool or a PSR-16 simple cache.
     * @throws InvalidArgumentException If $cache is neither a PSR-6 pool nor a PSR-16 cache.
     */
    public function __construct($cache)
    {
        if ($cache instanceof \Psr\Cache\CacheItemPoolInterface) {
            $this->psr6 = $cache;
            $this->psr16 = null;
        } else if ($cache instanceof \Psr\SimpleCache\CacheInterface) {
            $this->psr16 = $cache;
            $this->psr6 = null;
        } else {
            throw new InvalidArgumentException(
                'Cache must implement Psr\\Cache\\CacheItemPoolInterface (PSR-6) ' .
                'or Psr\\SimpleCache\\CacheInterface (PSR-16).'
            );
        }
    }

    /**
     * Fetch a cached token array.
     *
     * Fail-open: any cache error results in a null return (treated as a miss)
     * so a cache problem never breaks authentication.
     *
     * @param string $key The (already PSR-6-safe) cache key.
     * @return array|null The cached token array, or null on miss or any error.
     */
    public function get(string $key): ?array
    {
        try {
            if ($this->psr6 !== null) {
                $item = $this->psr6->getItem($key);
                if (!$item->isHit()) {
                    return null;
                }
                $value = $item->get();
            } else {
                $value = $this->psr16->get($key, null);
            }

            return is_array($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Store a token array with a time-to-live.
     *
     * Fail-open: any cache error is swallowed so that a cache write failure
     * never breaks the request.
     *
     * @param string $key The (already PSR-6-safe) cache key.
     * @param array $value The token array to store.
     * @param int $ttlSeconds Time-to-live in seconds.
     * @return void
     */
    public function set(string $key, array $value, int $ttlSeconds): void
    {
        try {
            if ($this->psr6 !== null) {
                $item = $this->psr6->getItem($key);
                $item->set($value);
                $item->expiresAfter($ttlSeconds);
                $this->psr6->save($item);
            } else {
                $this->psr16->set($key, $value, $ttlSeconds);
            }
        } catch (\Throwable) {
            // Fail-open: a cache write failure must not break the request.
        }
    }
}
