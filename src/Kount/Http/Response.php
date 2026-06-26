<?php

/**
 * Response.php file containing Kount_Http_Response class.
 *
 * @package Kount
 * @subpackage Http
 */

/**
 * HTTP response value object returned by the transport abstraction.
 *
 * @package Kount
 * @subpackage Http
 * @author Kount <custserv@kount.com>
 * @version $Id$
 * @copyright 2012 Kount, Inc. All Rights Reserved.
 */
class Kount_Http_Response
{
    /**
     * HTTP status code.
     * @var int
     */
    public $statusCode;

    /**
     * Raw response body.
     * @var string
     */
    public $body;

    /**
     * Transport-level error message, or null when there was no error.
     * @var string|null
     */
    public $errorMessage;

    /**
     * Transport-level error code (e.g. cURL errno). 0 when no error.
     * @var int
     */
    public $errorCode;

    /**
     * Constructor.
     *
     * @param int $statusCode HTTP status code.
     * @param string $body Raw response body.
     * @param string|null $errorMessage Transport-level error message.
     * @param int $errorCode Transport-level error code.
     */
    public function __construct($statusCode = 0, $body = '', $errorMessage = null, $errorCode = 0)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
    }

    /**
     * Whether this response represents an error.
     *
     * @return bool True when a transport error occurred or the status code is >= 400.
     */
    public function isError()
    {
        return $this->errorMessage !== null || $this->statusCode >= 400;
    }
} // end Kount_Http_Response
