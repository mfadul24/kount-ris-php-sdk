<?php

/**
 * Request.php file containing Kount_Http_Request class.
 *
 * @package Kount
 * @subpackage Http
 */

/**
 * HTTP request value object used by the transport abstraction.
 *
 * Carries everything a transport needs to perform a single HTTP call,
 * including optional SSL/cert options that are only meaningful for the
 * cURL transport. Headers are stored as an associative array of
 * name => value pairs.
 *
 * @package Kount
 * @subpackage Http
 * @author Kount <custserv@kount.com>
 * @version $Id$
 * @copyright 2012 Kount, Inc. All Rights Reserved.
 */
class Kount_Http_Request
{
    /**
     * HTTP method (e.g. 'POST').
     * @var string
     */
    public $method;

    /**
     * Target URL.
     * @var string
     */
    public $url;

    /**
     * Associative array of headers: name => value.
     * @var array
     */
    public $headers;

    /**
     * Raw request body.
     * @var string
     */
    public $body;

    /**
     * Connection timeout in seconds.
     * @var int
     */
    public $timeout;

    /**
     * SSL certificate type (e.g. 'PEM'). Null when not used.
     * @var string|null
     */
    public $sslCertType;

    /**
     * Path to the SSL certificate file. Null when not used.
     * @var string|null
     */
    public $sslCert;

    /**
     * Path to the SSL key file. Null when not used.
     * @var string|null
     */
    public $sslKey;

    /**
     * Password for the SSL key. Null when not used.
     * @var string|null
     */
    public $sslKeyPassword;

    /**
     * SSL version (cURL CURLOPT_SSLVERSION value). Null when not used.
     * @var int|null
     */
    public $sslVersion;

    /**
     * Whether to verify the peer (cURL CURLOPT_SSL_VERIFYPEER). Null when not used.
     * @var int|null
     */
    public $sslVerifyPeer;

    /**
     * Host verification level (cURL CURLOPT_SSL_VERIFYHOST). Null when not used.
     * @var int|null
     */
    public $sslVerifyHost;

    /**
     * Constructor.
     *
     * @param string $method HTTP method.
     * @param string $url Target URL.
     * @param array $headers Associative array of headers (name => value).
     * @param string $body Raw request body.
     * @param int $timeout Connection timeout in seconds.
     * @param string|null $sslCertType SSL certificate type.
     * @param string|null $sslCert Path to SSL certificate file.
     * @param string|null $sslKey Path to SSL key file.
     * @param string|null $sslKeyPassword Password for the SSL key.
     * @param int|null $sslVersion SSL version.
     * @param int|null $sslVerifyPeer Whether to verify the peer.
     * @param int|null $sslVerifyHost Host verification level.
     */
    public function __construct(
        $method,
        $url,
        array $headers = array(),
        $body = '',
        $timeout = 30,
        $sslCertType = null,
        $sslCert = null,
        $sslKey = null,
        $sslKeyPassword = null,
        $sslVersion = null,
        $sslVerifyPeer = null,
        $sslVerifyHost = null
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
        $this->timeout = $timeout;
        $this->sslCertType = $sslCertType;
        $this->sslCert = $sslCert;
        $this->sslKey = $sslKey;
        $this->sslKeyPassword = $sslKeyPassword;
        $this->sslVersion = $sslVersion;
        $this->sslVerifyPeer = $sslVerifyPeer;
        $this->sslVerifyHost = $sslVerifyHost;
    }
} // end Kount_Http_Request
