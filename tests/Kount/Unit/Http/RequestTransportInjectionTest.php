<?php

use PHPUnit\Framework\TestCase;

/**
 * Offline unit tests verifying that Kount_Ris_Request uses an injected
 * transport instead of performing any real network call.
 */
class RequestTransportInjectionTest extends TestCase
{
    /**
     * Build a non-migration-mode inquiry with API-key auth. We deliberately do
     * NOT call setPayment() to avoid Khash validation, and migration mode is
     * disabled so the constructor performs no network calls.
     *
     * @return Kount_Ris_Request_Inquiry
     */
    private function createInquiry()
    {
        $settings = new Kount_Ris_ArraySettings([
            'CONFIG_KEY' => 'bygdm9Cm2P3pUKDfdbWNlnDxg2juhTzfeM5Akm7K',
            'MERCHANT_ID' => '999666',
            'URL' => 'https://risk.test.kount.net',
            'API_KEY' => 'test-api-key',
            'VERS' => '0720',
            'CONNECT_TIMEOUT' => '30',
            'SDK' => 'PHP',
            'SDK_VERSION' => 'Sdk-Ris-PHP-0.0.0',
            'MIGRATION_MODE_ENABLED' => false,
        ]);

        $inquiry = new Kount_Ris_Request_Inquiry($settings);
        $inquiry->setMode('Q');
        $inquiry->setSessionId('abcdef0123456789abcdef0123456789');
        return $inquiry;
    }

    public function testInjectedTransportIsUsedAndResponseReturned()
    {
        $inquiry = $this->createInquiry();

        $fake = new class implements Kount_Http_Transport {
            public ?Kount_Http_Request $captured = null;

            public function send(Kount_Http_Request $request): Kount_Http_Response
            {
                $this->captured = $request;
                return new Kount_Http_Response(200, "VERS=0720\nMODE=Q\nAUTO=A\n", null, 0);
            }
        };

        $inquiry->setTransport($fake);

        $response = $inquiry->getResponse();

        // The fake transport was used (no real network).
        $this->assertNotNull($fake->captured);
        $this->assertSame('POST', $fake->captured->method);
        $this->assertSame('https://risk.test.kount.net', $fake->captured->url);
        // API-key headers present.
        $this->assertArrayHasKey('X-Kount-Api-Key', $fake->captured->headers);
        $this->assertSame('test-api-key', $fake->captured->headers['X-Kount-Api-Key']);
        $this->assertArrayHasKey('X-Kount-Merc-Id', $fake->captured->headers);
        $this->assertSame('999666', $fake->captured->headers['X-Kount-Merc-Id']);
        // Non-empty body.
        $this->assertNotEmpty($fake->captured->body);
        $this->assertStringContainsString('MODE=Q', $fake->captured->body);

        // A Kount_Ris_Response is returned.
        $this->assertInstanceOf(Kount_Ris_Response::class, $response);
    }

    public function testErrorStatusThrowsException()
    {
        $inquiry = $this->createInquiry();

        $fake = new class implements Kount_Http_Transport {
            public function send(Kount_Http_Request $request): Kount_Http_Response
            {
                return new Kount_Http_Response(500, 'Internal Server Error', null, 0);
            }
        };

        $inquiry->setTransport($fake);

        $this->expectException(Kount_Ris_Exception::class);
        $inquiry->getResponse();
    }
}
