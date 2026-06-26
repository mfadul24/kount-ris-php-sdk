<?php

namespace Kount\Tests\Unit\Http;

use Kount_Http_LoggingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;

/**
 * Offline unit tests for Kount_Http_LoggingHttpClient.
 */
class LoggingHttpClientTest extends TestCase
{
    public function testLogsRequestAndResponseAndReturnsDelegateResponse()
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', 'https://risk.test.kount.net/api')
            ->withBody($factory->createStream('PTOK=secret&MERC=900900'));
        $cannedResponse = $factory->createResponse(200)
            ->withBody($factory->createStream('{"AUTO":"A"}'));

        $delegate = $this->makeDelegate($cannedResponse);
        $logger = $this->makeLogger();

        $client = new Kount_Http_LoggingHttpClient($delegate, $logger);
        $response = $client->sendRequest($request);

        // The returned response is the delegate's response.
        $this->assertSame($cannedResponse, $response);

        // Request method, URI and body were logged.
        $requestLog = $this->findLog($logger->records, 'HTTP request');
        $this->assertNotNull($requestLog);
        $this->assertSame('info', $requestLog['level']);
        $this->assertSame('POST', $requestLog['context']['method']);
        $this->assertSame('https://risk.test.kount.net/api', $requestLog['context']['uri']);
        $this->assertSame('PTOK=secret&MERC=900900', $requestLog['context']['body']);

        // Response status and body were logged.
        $responseLog = $this->findLog($logger->records, 'HTTP response');
        $this->assertNotNull($responseLog);
        $this->assertSame('info', $responseLog['level']);
        $this->assertSame(200, $responseLog['context']['status']);
        $this->assertSame('{"AUTO":"A"}', $responseLog['context']['body']);
    }

    public function testRequestBodyIsStillReadableAfterCall()
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', 'https://risk.test.kount.net/api')
            ->withBody($factory->createStream('PTOK=secret&MERC=900900'));
        $cannedResponse = $factory->createResponse(200)
            ->withBody($factory->createStream('ok'));

        $client = new Kount_Http_LoggingHttpClient($this->makeDelegate($cannedResponse), $this->makeLogger());
        $client->sendRequest($request);

        // The rewind in the decorator means the body is still fully readable.
        $this->assertSame('PTOK=secret&MERC=900900', (string) $request->getBody());
    }

    public function testClientExceptionIsLoggedAndRethrown()
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', 'https://risk.test.kount.net/api')
            ->withBody($factory->createStream('body'));

        $exception = new class ('boom') extends \RuntimeException implements ClientExceptionInterface {
        };

        $delegate = new class ($exception) implements ClientInterface {
            private $exception;
            public function __construct($exception)
            {
                $this->exception = $exception;
            }
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $logger = $this->makeLogger();
        $client = new Kount_Http_LoggingHttpClient($delegate, $logger);

        $thrown = null;
        try {
            $client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $thrown = $e;
        }

        // The exception propagated (was not swallowed).
        $this->assertSame($exception, $thrown);

        // An error was logged.
        $errorLog = $this->findLog($logger->records, 'HTTP request failed');
        $this->assertNotNull($errorLog);
        $this->assertSame('error', $errorLog['level']);
    }

    private function makeDelegate(ResponseInterface $response): ClientInterface
    {
        return new class ($response) implements ClientInterface {
            private $response;
            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function makeLogger()
    {
        return new class extends AbstractLogger {
            public $records = array();
            public function log($level, $message, array $context = array()): void
            {
                $this->records[] = array(
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                );
            }
        };
    }

    private function findLog(array $records, string $message)
    {
        foreach ($records as $record) {
            if ($record['message'] === $message) {
                return $record;
            }
        }
        return null;
    }
}
