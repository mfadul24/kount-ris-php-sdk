<?php

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Offline unit tests for Kount_Http_Psr18Transport.
 */
class Psr18TransportTest extends TestCase
{
    public function testForwardsRequestAndReturnsResponse()
    {
        $factory = new Psr17Factory();

        // Anonymous PSR-18 client that captures the request and returns a canned response.
        $client = new class($factory) implements ClientInterface {
            public ?RequestInterface $captured = null;
            private Psr17Factory $factory;

            public function __construct(Psr17Factory $factory)
            {
                $this->factory = $factory;
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->captured = $request;
                $response = $this->factory->createResponse(200);
                return $response->withBody($this->factory->createStream('{"AUTO":"A"}'));
            }
        };

        $transport = new Kount_Http_Psr18Transport($client, $factory, $factory);

        $httpRequest = new Kount_Http_Request(
            'POST',
            'https://example.com/ris',
            array('X-Kount-Api-Key' => 'secret', 'X-Kount-Merc-Id' => '999666'),
            'MODE=Q&VERS=0720',
            30
        );

        $response = $transport->send($httpRequest);

        // Forwarded correctly.
        $this->assertNotNull($client->captured);
        $this->assertSame('POST', $client->captured->getMethod());
        $this->assertSame('https://example.com/ris', (string) $client->captured->getUri());
        $this->assertSame('secret', $client->captured->getHeaderLine('X-Kount-Api-Key'));
        $this->assertSame('999666', $client->captured->getHeaderLine('X-Kount-Merc-Id'));
        $this->assertSame('MODE=Q&VERS=0720', (string) $client->captured->getBody());

        // Response comes back.
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('{"AUTO":"A"}', $response->body);
        $this->assertFalse($response->isError());
        $this->assertNull($response->errorMessage);
    }

    public function testClientExceptionYieldsErrorResponse()
    {
        $factory = new Psr17Factory();

        $exception = new class('boom') extends RuntimeException implements ClientExceptionInterface {
        };

        $client = new class($exception) implements ClientInterface {
            private ClientExceptionInterface $exception;

            public function __construct(ClientExceptionInterface $exception)
            {
                $this->exception = $exception;
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $transport = new Kount_Http_Psr18Transport($client, $factory, $factory);

        $httpRequest = new Kount_Http_Request('POST', 'https://example.com/ris', array(), 'body', 30);
        $response = $transport->send($httpRequest);

        $this->assertTrue($response->isError());
        $this->assertSame('boom', $response->errorMessage);
        $this->assertSame(0, $response->errorCode);
    }
}
