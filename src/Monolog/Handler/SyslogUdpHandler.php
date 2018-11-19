<?php declare(strict_types=1);

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
 *
 * @author Jesper Skovgaard Nielsen <nulpunkt@gmail.com>
 */
class SyslogUdpHandler extends AbstractSyslogHandler
{
    protected $socket;
    protected $ident;

    /**
     * @param string     $host
     * @param int        $port
     * @param string|int $facility Either one of the names of the keys in $this->facilities, or a LOG_* facility constant
     * @param string|int $level    The minimum logging level at which this handler will be triggered
     * @param bool       $bubble   Whether the messages that are handled can bubble up the stack or not
     * @param string     $ident    Program name or tag for each log message.
     */
    public function __construct(string $host, int $port = 514, $facility = LOG_USER, $level = Logger::DEBUG, bool $bubble = true, string $ident = 'php')
    {
        parent::__construct($facility, $level, $bubble);

        $this->ident = $ident;

        $this->socket = new UdpSocket($host, $port ?: 514);
    }

    protected function write(array $record): void
    {
        $lines = $this->splitMessageIntoLines($record['formatted']);

        $header = $this->makeCommonSyslogHeader($this->logLevels[$record['level']]);

        foreach ($lines as $line) {
            $this->socket->write($line, $header);
        }
    }

    public function close(): void
    {
        $this->socket->close();
    }

    private function splitMessageIntoLines($message): array
    {
        if (is_array($message)) {
            $message = implode("\n", $message);
        }

        return preg_split('/$\R?^/m', (string) $message, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Make common syslog header (see rfc5424)
     */
    protected function makeCommonSyslogHeader(int $severity): string
    {
        $priority = $severity + $this->facility;

        if (!$pid = getmypid()) {
            $pid = '-';
        }

        if (!$hostname = gethostname()) {
            $hostname = '-';
        }

        return "<$priority>1 " .
            $this->getDateTime() . " " .
            $hostname . " " .
            $this->ident . " " .
            $pid . " - - ";
    }

    protected function getDateTime(): string
    {
        return date(\DateTime::RFC3339);
    }

    /**
     * Inject your own socket, mainly used for testing
     */
    public function setSocket(UdpSocket $socket): self
    {
        $this->socket = $socket;

        return $this;
    }
}
