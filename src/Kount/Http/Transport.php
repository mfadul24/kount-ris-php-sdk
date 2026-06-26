<?php

/**
 * Transport.php file containing Kount_Http_Transport interface.
 *
 * @package Kount
 * @subpackage Http
 */

/**
 * HTTP transport abstraction. Implementations perform the actual HTTP call.
 *
 * @package Kount
 * @subpackage Http
 * @author Kount <custserv@kount.com>
 * @version $Id$
 * @copyright 2012 Kount, Inc. All Rights Reserved.
 */
interface Kount_Http_Transport
{
    /**
     * Send a request and return the response.
     *
     * @param Kount_Http_Request $request The request value object.
     * @return Kount_Http_Response The response value object.
     */
    public function send(Kount_Http_Request $request): Kount_Http_Response;
} // end Kount_Http_Transport
