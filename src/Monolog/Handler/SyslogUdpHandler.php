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

use Monolog\Logger;
use Monolog\Handler\SyslogUdp\UdpSocket;

/**
 * A Handler for logging to a remote syslogd server.
 * Example usage (given you have a syslogd server on your local machine):
 * <code>
 * $logger = new \Monolog\Logger();
 * $handler = new SyslogUdpHandler("local5", "127.0.0.1");
 * $handler->setFormatter(new \Monolog\Formatter\LineFormatter());
 * $logger->pushHandler($handler);
 * $logger->warn("Hello from abroard!");
 * </code>
 */

class SyslogUdpHandler extends AbstractProcessingHandler
{
    protected $facility;

    protected $facilities = array(
            "local0" => 16,
            "local1" => 17,
            "local2" => 18,
            "local3" => 19,
            "local4" => 20,
            "local5" => 21,
            "local6" => 22,
            "local7" => 23
    );

    protected $severityMap = array(
        Logger::EMERGENCY => 0,
        Logger::ALERT => 1,
        Logger::CRITICAL => 2,
        Logger::ERROR => 3,
        Logger::WARNING => 4,
        Logger::NOTICE => 5,
        Logger::INFO => 6,
        Logger::DEBUG => 7
    );

    public function __construct($facility, $syslogdIp, $port = null)
    {
        $port = is_null($port) ? 514 : $port;
        $this->socket = new UdpSocket($syslogdIp, $port);

        $this->validateFacility($facility);
        $this->facility = $this->facilities[$facility];

    }

    protected function validateFacility($facility)
    {
        if (!is_string($facility) || !array_key_exists($facility, $this->facilities)) {
            throw new \InvalidArgumentException("Invalid syslog facility: $facility");
        }
    }

    protected function write(array $record)
    {
        $lines = $this->splitMessageIntoLines($record['formatted']);

        $header = $this->makeCommonSyslogHeader($this->getSeverity($record['level']));

        foreach ($lines as $line) {
            $this->socket->write($line, $header);
        }
    }

    public function close()
    {
        $this->socket->close();
    }

    protected function splitMessageIntoLines($message)
    {
        if (is_array($message)) {
            $message = implode("\n", $message);
        }
        return preg_split('/$\R?^/m', $message);
    }

    /**
     * Make common syslog header (see rfc5424)
     */
    protected function makeCommonSyslogHeader($severity)
    {
        $priority = $severity + ($this->facility << 3);
        return "<$priority>: ";
    }

    /**
     * Map the Monolog severity levels to syslogd.
     */
    protected function getSeverity($priority)
    {
        if (array_key_exists($priority, $this->severityMap)) {
            return $this->severityMap[$priority];
        } else {
            return Logger::INFO;
        }
    }

    /**
     * Inject your own socket, mainly used for testing
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;
    }
}
