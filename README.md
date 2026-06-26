# Kount RIS PHP SDK #

The Kount RIS PHP SDK contains the PHP SDK, tests, and build/package routines. This enables integrating the Kount fraud
fighting solution into your PHP app.

## Documentation ##

For official **integration documentation**, go
to [How to Integrate the RIS PHP SDK](https://developer.kount.com/hc/en-us/articles/5866429971604) on the Kount
Developer site.

## Custom HTTP client (PSR-18) ##

By default the SDK performs its HTTP calls with cURL, so no changes are
required for existing integrations. If you prefer to use your own
[PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client (for example Guzzle),
inject it together with [PSR-17](https://www.php-fig.org/psr/psr-17/) factories
via `setHttpClient()`. The injected client is used for both the RIS request and
the Payments Fraud token-refresh call.

```php
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$inquiry = new Kount_Ris_Request_Inquiry($settings);

$psr17 = new Psr17Factory(); // implements both PSR-17 factory interfaces
$guzzle = new Client();      // any Psr\Http\Client\ClientInterface

$inquiry->setHttpClient($guzzle, $psr17, $psr17);

// ... configure the inquiry as usual ...
$response = $inquiry->getResponse();
```

For advanced scenarios you can supply a fully custom transport implementing
`Kount_Http_Transport` via `setTransport(...)`.

> **Note:** mTLS / client-certificate authentication must be configured on the
> injected PSR-18 client itself. PSR-18 does not carry cURL certificate
> options, so the certificate settings used by the default cURL transport are
> ignored when a PSR-18 client is injected.

## Caching the authentication token (PSR-6 / PSR-16) ##

When Payments Fraud (migration) mode is enabled, the SDK obtains an OAuth
login/auth token from the Payments Fraud auth endpoint. By default this token is
held only in memory and is re-fetched in every new process.

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
  secret is stored in plaintext and the key is safe for PSR-6 (which forbids the
  characters `{}()/\@:`).
- The cached entry is stored with a TTL that matches the token's remaining
  lifetime (its `expires_at`), and the SDK reuses it until it is close to expiry.
- Caching is fail-open: if the cache backend errors on read or write, the SDK
  silently falls back to its normal in-memory / network behavior so a cache
  problem never breaks authentication.
- If no cache is injected, behavior is identical to previous versions (in-memory
  token only).

## Logging request & response payloads ##

Rather than a special flag inside the SDK, request and response payloads are
logged by composing a logging **middleware** with the PSR-18 HTTP client support
described above. `Kount_Http_LoggingHttpClient` is a reusable PSR-18
(`Psr\Http\Client\ClientInterface`) decorator that logs the request (method, URI,
body) and response (status, body) through any PSR-3 (`Psr\Log\LoggerInterface`)
logger before delegating to the wrapped client.

Wrap your real PSR-18 client with it and inject the wrapper via
`setHttpClient()`:

```php
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

// $guzzle implements Psr\Http\Client\ClientInterface
// $psr3Logger implements Psr\Log\LoggerInterface (e.g. Monolog)
$guzzle = new Client();
$logging = new Kount_Http_LoggingHttpClient($guzzle, $psr3Logger);

$psr17 = new Psr17Factory(); // implements both PSR-17 factory interfaces

$inquiry = new Kount_Ris_Request_Inquiry($settings);
$inquiry->setHttpClient($logging, $psr17, $psr17);

// Every RIS request, response, and token-refresh call is now logged.
$response = $inquiry->getResponse();
```

Behavior notes:

- The middleware wraps **any** PSR-18 client, so it can also be used standalone,
  independently of this SDK.
- Message bodies are read with a `(string)` cast and then rewound (when
  seekable), so the delegate and any downstream callers still see the full,
  unconsumed payload.
- If the wrapped client throws a `Psr\Http\Client\ClientExceptionInterface`, the
  error is logged and the exception is re-thrown (never swallowed).
- Be mindful that request payloads contain sensitive fields (e.g. the payment
  token `PTOK`); configure your PSR-3 logger / processors to redact them as
  required by your environment.

## Release Notes ##

For the complete **release notes history**, go
to [Kount RIS PHP SDK Release Notes History](https://developer.kount.com/hc/en-us/articles/10324810971540) on the Kount
Developer site.
