# Kount RIS PHP SDK #

The Kount RIS PHP SDK contains the PHP SDK, tests, and build/package routines. This enables integrating the Kount fraud
fighting solution into your PHP app.

## Documentation ##

For official **integration documentation**, go
to [How to Integrate the RIS PHP SDK](https://developer.kount.com/hc/en-us/articles/5866429971604) on the Kount
Developer site.

## Logging request & response payloads ##

The SDK can log the full request and response payloads to help with debugging.
The payment token (`PTOK`) is **always masked** as `[HIDDEN]` before logging, so
the raw payment token is never written to your logs.

### 1. Built-in flag on the request ###

Enable payload logging on any `Kount_Ris_Request` (e.g. an inquiry) with
`setPayloadLogging(true)`:

```php
$inquiry = new Kount_Ris_Request_Inquiry($settings);
// ... configure the inquiry ...
$inquiry->setPayloadLogging(true); // PTOK is masked in the logged payload

$response = $inquiry->getResponse();
```

When enabled, the request payload is logged at `info` level after the POST body
is built, and the raw response is logged at `info` level once received.

You can also turn it on through configuration by adding a truthy `PAYLOAD_LOGGING`
key to your `Kount_Ris_ArraySettings`. The flag is only read when the key is
present, so existing configurations are unaffected:

```php
$settings = new Kount_Ris_ArraySettings([
    // ... existing settings ...
    'PAYLOAD_LOGGING' => true,
]);
```

### 2. PSR-18 logging middleware ###

`Kount_Http_LoggingHttpClient` is a reusable PSR-18
(`Psr\Http\Client\ClientInterface`) decorator that logs the request
(method, URI, body) and response (status, body) via any PSR-3
(`Psr\Log\LoggerInterface`) logger before delegating to the wrapped client. It
wraps **any** PSR-18 client, so it can be composed with the SDK's PSR-18
HTTP-client support or used standalone in front of a client such as Guzzle:

```php
use Kount_Http_LoggingHttpClient;

// $guzzleClient implements Psr\Http\Client\ClientInterface
// $psr3Logger   implements Psr\Log\LoggerInterface (e.g. Monolog)
$logging = new Kount_Http_LoggingHttpClient($guzzleClient, $psr3Logger);

// then inject $logging wherever the SDK accepts a PSR-18 client
```

When combined with the SDK's PSR-18 HTTP-client support, this middleware
transparently logs every request/response payload. Message bodies are read with
a `(string)` cast and then rewound (when seekable), so the delegate and any
downstream callers still see the full, unconsumed payload. If the wrapped client
throws a `Psr\Http\Client\ClientExceptionInterface`, the error is logged and the
exception is re-thrown (never swallowed).

## Release Notes ##

For the complete **release notes history**, go
to [Kount RIS PHP SDK Release Notes History](https://developer.kount.com/hc/en-us/articles/10324810971540) on the Kount
Developer site.
