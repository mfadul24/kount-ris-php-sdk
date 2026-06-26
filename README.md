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

## Release Notes ##

For the complete **release notes history**, go
to [Kount RIS PHP SDK Release Notes History](https://developer.kount.com/hc/en-us/articles/10324810971540) on the Kount
Developer site.
