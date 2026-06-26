<?php

/**
 * CurlTransport.php file containing Kount_Http_CurlTransport class.
 *
 * @package Kount
 * @subpackage Http
 */

/**
 * Default cURL-based HTTP transport. Replicates the legacy cURL behavior so
 * that the SDK keeps working exactly as before when no client is injected.
 *
 * @package Kount
 * @subpackage Http
 * @author Kount <custserv@kount.com>
 * @version $Id$
 * @copyright 2012 Kount, Inc. All Rights Reserved.
 */
class Kount_Http_CurlTransport implements Kount_Http_Transport
{
    /**
     * Send a request using cURL.
     *
     * @param Kount_Http_Request $request The request value object.
     * @return Kount_Http_Response The response value object.
     */
    public function send(Kount_Http_Request $request): Kount_Http_Response
    {
        $ch = curl_init();

        // Convert the associative headers array to "Name: value" lines.
        $headers = array();
        foreach ($request->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $request->timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->body);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);

        // Apply SSL options only when set on the request value object so that
        // the RIS POST keeps VERIFYPEER=1, VERIFYHOST=2, SSLVERSION=6 and cert
        // options when present, while the token-refresh call sets none of them.
        if ($request->sslCertType !== null) {
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, $request->sslCertType);
        }
        if ($request->sslCert !== null) {
            curl_setopt($ch, CURLOPT_SSLCERT, $request->sslCert);
        }
        if ($request->sslKey !== null) {
            curl_setopt($ch, CURLOPT_SSLKEY, $request->sslKey);
        }
        if ($request->sslKeyPassword !== null) {
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $request->sslKeyPassword);
        }
        if ($request->sslVersion !== null) {
            curl_setopt($ch, CURLOPT_SSLVERSION, $request->sslVersion);
        }
        if ($request->sslVerifyPeer !== null) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $request->sslVerifyPeer);
        }
        if ($request->sslVerifyHost !== null) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $request->sslVerifyHost);
        }

        $output = curl_exec($ch);

        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo) {
            return new Kount_Http_Response(0, (string) $output, $curlError, $curlErrNo);
        }

        return new Kount_Http_Response((int) $httpCode, (string) $output, null, 0);
    }
} // end Kount_Http_CurlTransport
