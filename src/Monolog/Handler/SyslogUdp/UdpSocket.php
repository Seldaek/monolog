<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\SyslogUdp;

class UdpSocket
{
    const DATAGRAM_MAX_LENGTH = 2048;

    public function __construct($ip, $port = 514)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    public function write($line, $header = "")
    {
        $remaining = $line;
        while (!is_null($remaining)) {
            list($chunk, $remaining) = $this->splitLineIfNessesary($remaining, $header);
            $this->send($chunk);
        }
    }

    public function close()
    {
        socket_close($this->socket);
    }

    protected function send($chunk)
    {
        socket_sendto($this->socket, $chunk, strlen($chunk), $flags = 0, $this->ip, $this->port);
    }

    protected function splitLineIfNessesary($line, $header)
    {
        if ($this->shouldSplitLine($line, $header)) {
            $chunkSize = self::DATAGRAM_MAX_LENGTH - strlen($header);
            $chunk = $header . substr($line, 0, $chunkSize);
            $remaining = substr($line, $chunkSize);
        } else {
            $chunk = $header . $line;
            $remaining = null;
        }

        return array($chunk, $remaining);
    }

    protected function shouldSplitLine($remaining, $header)
    {
        return strlen($header.$remaining) > self::DATAGRAM_MAX_LENGTH;
    }
}
