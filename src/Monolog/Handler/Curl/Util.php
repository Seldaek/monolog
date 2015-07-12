<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Curl;

class Util
{
    private static $retriableErrorCodes = array(
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_COULDNT_CONNECT,
        CURLE_HTTP_NOT_FOUND,
        CURLE_READ_ERROR,
        CURLE_OPERATION_TIMEOUTED,
        CURLE_HTTP_POST_ERROR,
        CURLE_SSL_CONNECT_ERROR,
    );

    /**
     * Executes a CURL request with optional retries and exception on failure
     *
     * @param resource $ch curl handler
     * @throws \RuntimeException
     */
    public static function execute($ch, $retries = 5, $closeAfterDone = true)
    {
        while ($retries--) {
            if (curl_exec($ch) === false) {

                if (false === in_array(curl_errno($ch), self::$retriableErrorCodes, true) || !$retries) {
                    if ($closeAfterDone) {
                        curl_close($ch);
                    }

                    throw new \RuntimeException(sprintf('Curl error (code %s): %s', curl_errno($ch), curl_error($ch)));
                }

                continue;
            }

            if ($closeAfterDone) {
                curl_close($ch);
            }
            break;
        }
    }
}
