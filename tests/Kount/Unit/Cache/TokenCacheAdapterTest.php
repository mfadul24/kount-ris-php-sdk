<?php

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Offline unit tests for Kount_Cache_TokenCacheAdapter.
 *
 * Uses minimal in-memory PSR-6 and PSR-16 fakes (defined inline as anonymous
 * classes) so no real cache backend or network is required.
 */
class TokenCacheAdapterTest extends TestCase
{
    /**
     * Build a minimal in-memory PSR-16 cache.
     */
    private function makePsr16(): CacheInterface
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
     * Build a minimal in-memory PSR-6 cache item pool.
     */
    private function makePsr6(): CacheItemPoolInterface
    {
        return new class implements CacheItemPoolInterface {
            private array $store = array();

            public function getItem(string $key): CacheItemInterface
            {
                $hit = array_key_exists($key, $this->store);
                $value = $hit ? $this->store[$key] : null;
                return new class($key, $value, $hit) implements CacheItemInterface {
                    private string $k;
                    private mixed $v;
                    private bool $hit;

                    public function __construct(string $k, mixed $v, bool $hit)
                    {
                        $this->k = $k;
                        $this->v = $v;
                        $this->hit = $hit;
                    }

                    public function getKey(): string
                    {
                        return $this->k;
                    }

                    public function get(): mixed
                    {
                        return $this->v;
                    }

                    public function isHit(): bool
                    {
                        return $this->hit;
                    }

                    public function set(mixed $value): static
                    {
                        $this->v = $value;
                        $this->hit = true;
                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(\DateInterval|int|null $time): static
                    {
                        return $this;
                    }
                };
            }

            public function getItems(array $keys = array()): iterable
            {
                $out = array();
                foreach ($keys as $key) {
                    $out[$key] = $this->getItem($key);
                }
                return $out;
            }

            public function hasItem(string $key): bool
            {
                return array_key_exists($key, $this->store);
            }

            public function clear(): bool
            {
                $this->store = array();
                return true;
            }

            public function deleteItem(string $key): bool
            {
                unset($this->store[$key]);
                return true;
            }

            public function deleteItems(array $keys): bool
            {
                foreach ($keys as $key) {
                    $this->deleteItem($key);
                }
                return true;
            }

            public function save(CacheItemInterface $item): bool
            {
                $this->store[$item->getKey()] = $item->get();
                return true;
            }

            public function saveDeferred(CacheItemInterface $item): bool
            {
                return $this->save($item);
            }

            public function commit(): bool
            {
                return true;
            }
        };
    }

    public function testPsr16RoundTrip(): void
    {
        $adapter = new Kount_Cache_TokenCacheAdapter($this->makePsr16());
        $token = array('access_token' => 'abc', 'expires_at' => 123);

        $this->assertNull($adapter->get('missing'));
        $adapter->set('k', $token, 300);
        $this->assertSame($token, $adapter->get('k'));
    }

    public function testPsr6RoundTrip(): void
    {
        $adapter = new Kount_Cache_TokenCacheAdapter($this->makePsr6());
        $token = array('access_token' => 'xyz', 'expires_at' => 456);

        $this->assertNull($adapter->get('missing'));
        $adapter->set('k', $token, 300);
        $this->assertSame($token, $adapter->get('k'));
    }

    public function testConstructorRejectsNonPsr(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Kount_Cache_TokenCacheAdapter(new stdClass());
    }

    public function testFailOpenOnThrowingPsr16Cache(): void
    {
        $throwing = new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed
            {
                throw new RuntimeException('boom');
            }

            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
            {
                throw new RuntimeException('boom');
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return array();
            }

            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $adapter = new Kount_Cache_TokenCacheAdapter($throwing);

        // get() must swallow the error and return null (cache miss).
        $this->assertNull($adapter->get('k'));

        // set() must swallow the error and not propagate the exception.
        $adapter->set('k', array('a' => 1), 300);
        $this->assertTrue(true);
    }
}
