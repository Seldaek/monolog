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
use Monolog\Handler\AbstractSyslogHandler;
use Monolog\Formatter\LineFormatter;

/**
 * RelpHandler connects to a RELP Server to send a syslog message.
 *
 *   Example:
 *
 *    $relp = new RelpHandler(array('host' => $argv[1], 'port' => $argv[2]));
 *    $relp->open();
 *    $relp->syslog('<174>2017-05-11T08:49:28.006011-04:00 dmdev54 TESTING RELP');
 *    $relp->close();
 *
 * @see  http://www.rsyslog.com/doc/relp.html
 *
 *   Example pcap:
 *
 *    29311 syslog 187 <43>2017-05-11T08:49:28.717876-04:00 gw-g-rsyslog811 rsyslogd-2165: netstream session 0x80544b800 from 10.2.4.254 will be closed due to error  [v8.11.0 try http://www.rsyslog.com/e/2165 ]
 *    29311 rsp 6 200 OK
 *
 *  @author Derek Marcotte <554b8425@razorfever.net>
 *
 */
class RelpHandler extends AbstractSyslogHandler
{
    const TIMEOUT = 1;

    const RELP_VERSION = 0;
    const RELP_CLIENT = 'phprelp,0.0.0,http://www.experiencepoint.com/';

    const RELP_OK = '200';

    const CMD_OPEN = 'open';
    const CMD_CLOSE = 'close';
    const CMD_SYSLOG = 'syslog';

    private $_hostname = 'localhost';
    private $_port = 2514;
    private $_ident = 'php';

    private $_ip = null;
    private $_socket = null;

    private $_txn = 1;

    private $_isOpen = false;

    private $_datetime;

    public function __construct($options = array(), $facility = LOG_USER, $level = Logger::DEBUG, $bubble = true) {
        parent::__construct($facility, $level, $bubble);

        if (array_key_exists('url', $options)) {
            $options = array_merge($options, parse_url($options['url']));

            if (!array_key_exists('host', $options) ) {
                throw new \InvalidArgumentException('Host not found in url, try adding port.');
            }
        }

        foreach ($options as $key => $value) {
            switch($key) {
            case 'host':
                $this->setHost($value);
                break;
            case 'port':
                $this->setPort($value);
                break;
            case 'ident':
                $this->_ident = $value;
                break;
            }
        }
    }

    public function setHost($host) {
        if (filter_var($host, FILTER_VALIDATE_IP, array('flags' => FILTER_FLAG_IPV4 ) ) ) {
            $this->_ip = $host;
            return;
        }

        $this->_hostname = $host;
    }

    public function setPort($port) {
        $tmp = (int) $port;
        if ($tmp < 1 || $tmp > 65535) {
            throw new \RangeException('Tried to set an invalid port.');
        }

        $this->_port = $port;
    }

    private function _connectSocket() {
        $s =& $this->_socket;

        if (is_null($this->_ip) ) {
            $this->_ip = gethostbyname($this->_hostname);
            if ($this->_ip == $this->_hostname) {
                $this->_ip = null;
                throw new \RuntimeException('Could not resolve host address.');
            }
        }

        $s = stream_socket_client(
            sprintf('tcp://%s:%d', $this->_ip, $this->_port),
            $errno,
            $errstr,
            RelpHandler::TIMEOUT
        );
        if (!is_resource($s) ) {
            $s = null;
            throw new \RuntimeException('Unable to create socket.');
        }
        if (!stream_set_timeout($s, RelpHandler::TIMEOUT)) {
            $this->_disconnectSocket();
            throw new \RuntimeException('Unable to set timeout.');
        }

        return;
    }

    private function _disconnectSocket() {
        fclose($this->_socket);
        $this->_socket = null;
    }

    private function _isSocketConnected() {
        return !is_null($this->_socket);
    }

    private function _relpCommand($type, $message = null) {
        switch ($type) {
        case RelpHandler::CMD_OPEN:
            $message = sprintf("relp_version=%d\nrelp_software=%s\ncommands=%s",
                RelpHandler::RELP_VERSION,
                RelpHandler::RELP_CLIENT,
                RelpHandler::CMD_SYSLOG
            );
            break;
        case RelpHandler::CMD_CLOSE:
            // force emtpy message
            $message = null;
            break;
        }

        $txn = $this->_txn;
        $this->_txn++;

        if (is_null($message) ) {
            return sprintf("%d %s\n", $txn, $type);
        }

        $message = trim($message);
        return sprintf("%d %s %d %s\n", $txn, $type, strlen($message), $message);
    }

    private function _relpSendCommand($command) {
        $this->_fwriteAll($command);
    }

    private function _relpReadResponse() {
        $matches = array();

        // it's at least one line
        $firstline = $this->_freadLine();
        if (preg_match('/\d+ (?P<type>rsp|serverclose) (?P<length>\d+)( (?P<message>(?P<code>\d+) (?P<status>\w+).*))?/', $firstline, $matches) == 0) {
            throw new \UnexpectedValueException('Invalid response:' . $firstline);
        }

        if ($matches['type'] == 'serverclose') {
            return '0';
        }

        $message = $matches['message'];
        // Drain expected bytes from socket.
        while (strlen($message) < $matches['length']) {
            if ($message == $matches['message']) {
                // The first LF gets eaten for some reason.
                $message .= "\n";
            }
            $message .= $this->_freadLine();
        }
        return $matches['code'];
    }

    private function _fwriteAll($message) {
        for ($written = 0; $written < strlen($message); $written += $fwrite) {
            $fwrite = fwrite($this->_socket, substr($message, $written));
            if ($fwrite === false) {
                throw new \RuntimeException('Error writing to socket.');
            }

            $info = stream_get_meta_data($this->_socket);
            if ($info['timed_out']) {
                throw new \RuntimeException('Timed out writing to socket.');
            }
        }
    }

    private function _freadLine() {
        $res = fgets($this->_socket);
        if ($res === false) {
            throw new \RuntimeException('Could not read from socket.');
        }
        $info = stream_get_meta_data($this->_socket);
        if ($info['timed_out']) {
            throw new \RuntimeException('Timed out reading from socket.');
        }

        return $res;
    }

    public function isOpen() {
        return $this->_isSocketConnected() && $this->_isOpen;
    }

    public function open() {
        if ($this->isOpen() ) {
            throw new \LogicException('Connection already open.');
        }

        $this->_connectSocket();
        $this->_relpSendCommand($this->_relpCommand(RelpHandler::CMD_OPEN));

        $res = $this->_relpReadResponse();
        if ($res == RelpHandler::RELP_OK) {
            $this->_isOpen = true;
        }

        return $res;
    }

    public function close() {
        if (!$this->isOpen() ) {
            throw new \LogicException('Connection not open.');
        }

        $this->_relpSendCommand($this->_relpCommand(RelpHandler::CMD_CLOSE));
        $res = $this->_relpReadResponse();
        if ($res == "0") {
            $this->_isOpen = false;
        }

        $this->_disconnectSocket();
        return $res;
    }

    public function syslog($message) {
        if (!$this->isOpen() ) {
            throw new \LogicException('Connection not open.');
        }

        $this->_relpSendCommand($this->_relpCommand(RelpHandler::CMD_SYSLOG, $message));
        return $this->_relpReadResponse();
    }

    protected function write(array $record) {
        // Want a timestamp from when the request comes in, not when
        // the message is sent.
        $this->_datetime = date(\DateTime::RFC3339);

        if (!$this->isOpen() ) {
            if ($this->open() != RelpHandler::RELP_OK) {
                throw new \RuntimeException('Could not open RELP connection.');
            }
        }

        $message = $this->buildSyslogMessage($record);

        if ($this->syslog($message) != RelpHandler::RELP_OK) {
            throw new \RuntimeException('Could not write RELP message.');
        }
    }

    private function buildSyslogMessage(array $record) {
        // NOTE: priority encodes Monolog channel + level.
        $priority = $this->facility + $this->logLevels[$record['level']];

        if (!$pid = getmypid()) {
            $pid = '-';
        }

        if (!$hostname = gethostname()) {
            $hostname = '-';
        }

        // 29311 syslog 187 <43>2017-05-11T08:49:28.717876-04:00 gw-g-rsyslog811 rsyslogd-2165: netstream session 0x80544b800 from 10.2.4.254 will be closed due to error  [v8.11.0 try http://www.rsyslog.com/e/2165 ]
        return sprintf(
            '<%d>%s %s %s[%d]: %s',
            $priority,
            $this->_datetime,
            $hostname,
            $this->_ident,
            $pid,
            $record['formatted']
        );
    }

    protected function getDefaultFormatter() {
        return new LineFormatter('%message% %context% %extra%');
    }

}
