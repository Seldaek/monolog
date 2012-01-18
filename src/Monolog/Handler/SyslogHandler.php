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

/**
 * Logs to syslog service.
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $syslog = new SyslogHandler('myfacility', 'local6');
 *   $formatter = new LineFormatter("%channel%.%level_name%: %message% %extra%");
 *   $syslog->setFormatter($formatter);
 *   $log->pushHandler($syslog);
 *
 * @author Sven Paulus <sven@karlsruhe.org>
 */
class SyslogHandler extends AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to syslog log priorities.
     */
    private $logLevels = array(
        Logger::DEBUG    => LOG_DEBUG,
        Logger::INFO     => LOG_INFO,
        Logger::WARNING  => LOG_WARNING,
        Logger::ERROR    => LOG_ERR,
        Logger::CRITICAL => LOG_CRIT,
        Logger::ALERT    => LOG_ALERT,
    );

    /**
     * List of valid log facility names.
     */
    private $facilities = array(
        'auth'     => LOG_AUTH,
        'authpriv' => LOG_AUTHPRIV,
        'cron'     => LOG_CRON,
        'daemon'   => LOG_DAEMON,
        'kern'     => LOG_KERN,
        'lpr'      => LOG_LPR,
        'mail'     => LOG_MAIL,
        'news'     => LOG_NEWS,
        'syslog'   => LOG_SYSLOG,
        'user'     => LOG_USER,
        'uucp'     => LOG_UUCP,
    );
    
    /**
     * Fields for use with external syslog servers
     */
    private $ident = '';
    private $facility = '';
    private $socket;
     

    /**
     * @param string $ident
     * @param mixed $facility
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     * @param string $host Where to send the syslog data
     */
    public function __construct($ident, $facility = LOG_USER, $level = Logger::DEBUG, $bubble = true, $host = 'localhost', $port = '514')
    {
        parent::__construct($level, $bubble);

        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->facilities['local0'] = LOG_LOCAL0;
            $this->facilities['local1'] = LOG_LOCAL1;
            $this->facilities['local2'] = LOG_LOCAL2;
            $this->facilities['local3'] = LOG_LOCAL3;
            $this->facilities['local4'] = LOG_LOCAL4;
            $this->facilities['local5'] = LOG_LOCAL5;
            $this->facilities['local6'] = LOG_LOCAL6;
            $this->facilities['local7'] = LOG_LOCAL7;
        }

        // convert textual description of facility to syslog constant
        if (array_key_exists(strtolower($facility), $this->facilities)) {
            $facility = $this->facilities[strtolower($facility)];
        } else if (!in_array($facility, array_values($this->facilities), true)) {
            throw new \UnexpectedValueException('Unknown facility value "'.$facility.'" given');
        }

	if ($host === 'localhost')
	{
		if (!openlog($ident, LOG_PID, $facility)) {
		    throw new \LogicException('Can\'t open syslog for ident "'.$ident.'" and facility "'.$facility.'"');
		}
	}
	else
	{
		$this->ident = $ident;
		$this->facility = $facility;
		$this->socket = fsockopen("udp://".$host, $port, $errno, $errstr);
		stream_set_blocking($this->socket, 0); // Non-blocking socket
	}
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
	if ($this->socket)
	{
		fclose($this->socket);
	}
    
        closelog();
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
	if (!$this->socket)
	{	
		syslog($this->logLevels[$record['level']], (string) $record['formatted']);
	}
	else
	{
            $actualtime = time();
            $month      = date("M", $actualtime);
            $day        = substr("  ".date("j", $actualtime), -2);
            $hhmmss     = date("H:i:s", $actualtime);
            $timestamp  = $month." ".$day." ".$hhmmss;
            
            $pri    = "<".($this->facility*8 + $this->logLevels[$record['level']]).">";
            $header = $timestamp." WEBSERVER";
            
            $msg = $this->ident.": ".$_SERVER["SERVER_NAME"]." ".$_SERVER["SERVER_ADDR"]." ".(string) $record['formatted'];

            $message = substr($pri.$header." ".$msg, 0, 1024);
            
            if ($this->socket)
            {
                fwrite($this->socket, $message);
            }	
	}
    }
}














