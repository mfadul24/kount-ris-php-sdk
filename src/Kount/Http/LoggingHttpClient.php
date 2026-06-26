<?php

/**
 * LoggingHttpClient.php file containing Kount_Http_LoggingHttpClient class.
 *
 * @package Kount
 * @subpackage Http
 */

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * A PSR-18 logging middleware (decorator).
 *
 * This decorator wraps ANY {@see Psr\Http\Client\ClientInterface} and logs the
 * outgoing request (method, URI and body) and the incoming response (status and
 * body) via a PSR-3 {@see Psr\Log\LoggerInterface} before delegating to the
 * wrapped client. It is generic: it can be composed with the SDK's PSR-18
 * HTTP-client support to transparently log every request/response payload, or
 * used standalone in front of any PSR-18 client (e.g. Guzzle).
 *
 * Reading a PSR-7 message body via (string) cast consumes the underlying
 * stream, so after reading each body this decorator rewinds it (when seekable)
 * to ensure the delegate and downstream callers still see the full payload.
 *
 * @package Kount
 * @subpackage Http
 * @author Kount <custserv@kount.com>
 * @copyright 2012 Kount, Inc. All Rights Reserved.
 */
class Kount_Http_LoggingHttpClient implements ClientInterface
{
    /**
     * The wrapped PSR-18 client that performs the actual request.
     * @var ClientInterface
     */
    private $delegate;

    /**
     * The PSR-3 logger used to log request and response payloads.
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param ClientInterface $delegate The PSR-18 client to wrap.
     * @param LoggerInterface $logger The PSR-3 logger to log payloads with.
     */
    public function __construct(ClientInterface $delegate, LoggerInterface $logger)
    {
        $this->delegate = $delegate;
        $this->logger = $logger;
    }

    /**
     * Send a PSR-7 request and return a PSR-7 response.
     *
     * Logs the request method, URI and body at info level, delegates to the
     * wrapped client, then logs the response status and body at info level.
     * Bodies are rewound after reading so they remain readable downstream. A
     * thrown {@see ClientExceptionInterface} is logged at error level and
     * re-thrown (never swallowed).
     *
     * @param RequestInterface $request The request to send.
     * @return ResponseInterface The response from the wrapped client.
     * @throws ClientExceptionInterface If the wrapped client throws.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $requestBody = $this->readBody($request);
        $this->logger->info('HTTP request', array(
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'body' => $requestBody,
        ));

        try {
            $response = $this->delegate->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('HTTP request failed', array(
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'exception' => $e->getMessage(),
            ));
            throw $e;
        }

        $responseBody = $this->readBody($response);
        $this->logger->info('HTTP response', array(
            'status' => $response->getStatusCode(),
            'body' => $responseBody,
        ));

        return $response;
    }

    /**
     * Read a PSR-7 message body as a string, rewinding it afterwards when the
     * underlying stream is seekable so the body remains readable downstream.
     *
     * @param RequestInterface|ResponseInterface $message The message to read.
     * @return string The full body contents.
     */
    private function readBody($message)
    {
        $body = $message->getBody();
        $contents = (string) $body;
        if ($body->isSeekable()) {
            $body->rewind();
        }
        return $contents;
    }
} // end Kount_Http_LoggingHttpClient
