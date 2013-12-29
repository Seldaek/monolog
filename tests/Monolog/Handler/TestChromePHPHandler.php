<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

class TestChromePHPHandler
    extends ChromePHPHandler
{
    protected $headers = array();

    public static function reset()
    {
        self::$initialized  = false;
        self::$overflowed   = false;
        self::$json['rows'] = array();
    }

    protected function sendHeader(
        $header,
        $content
    ) {
        $this->headers[$header] = $content;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
