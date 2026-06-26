<?php

namespace Kount\Tests\Unit\Ris;

use Kount_Log_Binding_Logger;
use Kount_Ris_ArraySettings;
use Kount_Ris_Request_Inquiry;
use PHPUnit\Framework\TestCase;

/**
 * Offline unit tests for the payload logging / masking behaviour added to
 * Kount_Ris_Request. No network access is performed: the masking helper is
 * exercised directly via a test subclass.
 */
class PayloadLoggingTest extends TestCase
{
    private const PTOK = '0007380568572514';

    private function makeSettings(): Kount_Ris_ArraySettings
    {
        // Non-migration mode settings mirroring UtilityHelperTest.
        return new Kount_Ris_ArraySettings(array(
            'CONFIG_KEY' => 'fakeconfigkey',
            'MERCHANT_ID' => '900900',
            'URL' => 'https://risk.test.kount.net',
            'API_KEY' => 'fake-api-key',
            'VERS' => '0720',
            'CONNECT_TIMEOUT' => '30',
            'SDK' => 'PHP',
            'SDK_VERSION' => 'Sdk-Ris-PHP-0.0.0',
            'MIGRATION_MODE_ENABLED' => false,
        ));
    }

    private function makeInquiry(): PayloadLoggingTestInquiry
    {
        $inquiry = new PayloadLoggingTestInquiry($this->makeSettings());
        $inquiry->setMode('Q');
        // Set PTOK directly to avoid Khash hashing (offline, no real config key).
        $inquiry->setParm('PTOK', self::PTOK);
        $inquiry->setParm('PTYP', 'CARD');
        return $inquiry;
    }

    public function testMaskingReplacesPtokWithHidden()
    {
        $inquiry = $this->makeInquiry();
        $formatted = $inquiry->maskPayloadForLoggingPublic(array(
            'MERC' => '900900',
            'PTOK' => self::PTOK,
            'PTYP' => 'CARD',
        ));

        $this->assertStringContainsString('[PTOK]=[HIDDEN]', $formatted);
        $this->assertStringNotContainsString(self::PTOK, $formatted);
    }

    public function testMaskingKeepsNonSensitiveValues()
    {
        $inquiry = $this->makeInquiry();
        $formatted = $inquiry->maskPayloadForLoggingPublic(array(
            'MERC' => '900900',
            'PTOK' => self::PTOK,
        ));

        $this->assertStringContainsString('[MERC]=900900', $formatted);
    }

    public function testSetPayloadLoggingIsFluent()
    {
        $inquiry = $this->makeInquiry();
        $this->assertSame($inquiry, $inquiry->setPayloadLogging(true));
    }

    public function testLoggerCanBeSwapped()
    {
        $inquiry = $this->makeInquiry();
        $logger = new RecordingPayloadLogger();
        $inquiry->setLoggerForTest($logger);
        $inquiry->setPayloadLogging(true);

        // Drive the masking helper directly; assert the raw token never appears.
        $formatted = $inquiry->maskPayloadForLoggingPublic($inquiry->dataForTest());
        $this->assertStringNotContainsString(self::PTOK, $formatted);
        $this->assertStringContainsString('[PTOK]=[HIDDEN]', $formatted);
    }
}

/**
 * Test subclass exposing protected internals for offline testing.
 */
class PayloadLoggingTestInquiry extends Kount_Ris_Request_Inquiry
{
    public function setLoggerForTest($logger)
    {
        $this->logger = $logger;
    }

    public function maskPayloadForLoggingPublic(array $data)
    {
        return $this->maskPayloadForLogging($data);
    }

    public function dataForTest(): array
    {
        return $this->data;
    }
}

/**
 * Minimal recording logger implementing the SDK logger interface.
 */
class RecordingPayloadLogger implements Kount_Log_Binding_Logger
{
    public array $messages = array();

    public function debug($message, $exception = null)
    {
        $this->messages[] = array('debug', $message);
    }

    public function info($message, $exception = null)
    {
        $this->messages[] = array('info', $message);
    }

    public function warn($message, $exception = null)
    {
        $this->messages[] = array('warn', $message);
    }

    public function error($message, $exception = null)
    {
        $this->messages[] = array('error', $message);
    }

    public function fatal($message, $exception = null)
    {
        $this->messages[] = array('fatal', $message);
    }

    public function getRisLogger()
    {
        return false;
    }
}
