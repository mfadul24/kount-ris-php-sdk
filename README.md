# Kount RIS PHP SDK #

The Kount RIS PHP SDK contains the PHP SDK, tests, and build/package routines. This enables integrating the Kount fraud
fighting solution into your PHP app.

## Documentation ##

For official **integration documentation**, go
to [How to Integrate the RIS PHP SDK](https://developer.kount.com/hc/en-us/articles/5866429971604) on the Kount
Developer site.

## Caching the authentication token (PSR-6 / PSR-16) ##

When Payments Fraud (migration) mode is enabled, the SDK obtains an
OAuth login/auth token from the Payments Fraud auth endpoint. By default this
token is held only in memory and is re-fetched in every new process.

You can inject any [PSR-6](https://www.php-fig.org/psr/psr-6/)
(`Psr\Cache\CacheItemPoolInterface`) or
[PSR-16](https://www.php-fig.org/psr/psr-16/) (`Psr\SimpleCache\CacheInterface`)
cache so the token is persisted across request instances and processes:

```php
// $cache is any PSR-6 cache pool OR PSR-16 simple cache instance.
$inquiry = new Kount_Ris_Request_Inquiry($settings);
$inquiry->setTokenCache($cache);

$response = $inquiry->getResponse();
```

Notes:

- The token is keyed per auth-url + client-id
  (`'kount_ris_pf_token_' . hash('sha256', $authUrl . '|' . $clientId)`), so no
  secret is stored in plaintext and the key is safe for PSR-6 (which forbids
  the characters `{}()/\@:`).
- The cached entry is stored with a TTL that matches the token's remaining
  lifetime (its `expires_at`), and the SDK reuses it until it is close to
  expiry.
- Caching is fail-open: if the cache backend errors on read or write, the SDK
  silently falls back to its normal in-memory / network behavior so a cache
  problem never breaks authentication.
- If no cache is injected, behavior is identical to previous versions (in-memory
  token only).

## Release Notes ##

For the complete **release notes history**, go
to [Kount RIS PHP SDK Release Notes History](https://developer.kount.com/hc/en-us/articles/10324810971540) on the Kount
Developer site.
