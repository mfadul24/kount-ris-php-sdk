<?php

/**
 * Psr18Transport.php file containing Kount_Http_Psr18Transport class.
 *
 * @package Kount
 * @subpackage Http
 */

/**
 * PSR-18 based HTTP transport. Delegates the actual HTTP call to an injected
 * Psr\Http\Client\ClientInterface, building the PSR-7 request via PSR-17
 * factories.
 *
 * NOTE: client TLS/mTLS (certificate authentication) must be configured on the
 * injected client itself. PSR-18 does not carry cURL certificate options, so
 * the SSL options on Kount_Http_Request are ignored by this transport.
 *
 * @package Kount
 * @subpackage Http
 * @author Kount <custserv@kount.com>
 * @version $Id$
 * @copyright 2012 Kount, Inc. All Rights Reserved.
 */
class Kount_Http_Psr18Transport implements Kount_Http_Transport
{
    /**
     * The PSR-18 HTTP client.
     * @var \Psr\Http\Client\ClientInterface
     */
    private $client;

    /**
     * The PSR-17 request factory.
     * @var \Psr\Http\Message\RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * The PSR-17 stream factory.
     * @var \Psr\Http\Message\StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * Constructor.
     *
     * @param \Psr\Http\Client\ClientInterface $client The PSR-18 HTTP client.
     * @param \Psr\Http\Message\RequestFactoryInterface $requestFactory PSR-17 request factory.
     * @param \Psr\Http\Message\StreamFactoryInterface $streamFactory PSR-17 stream factory.
     */
    public function __construct(
        \Psr\Http\Client\ClientInterface $client,
        \Psr\Http\Message\RequestFactoryInterface $requestFactory,
        \Psr\Http\Message\StreamFactoryInterface $streamFactory
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Send a request using the injected PSR-18 client.
     *
     * @param Kount_Http_Request $request The request value object.
     * @return Kount_Http_Response The response value object.
     */
    public function send(Kount_Http_Request $request): Kount_Http_Response
    {
        try {
            $req = $this->requestFactory->createRequest($request->method, $request->url);
            foreach ($request->headers as $name => $value) {
                $req = $req->withHeader($name, $value);
            }
            $req = $req->withBody($this->streamFactory->createStream($request->body));

            $psrResponse = $this->client->sendRequest($req);

            return new Kount_Http_Response(
                $psrResponse->getStatusCode(),
                (string) $psrResponse->getBody(),
                null,
                0
            );
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            return new Kount_Http_Response(0, '', $e->getMessage(), 0);
        } catch (\Throwable $e) {
            return new Kount_Http_Response(0, '', $e->getMessage(), 0);
        }
    }
} // end Kount_Http_Psr18Transport
