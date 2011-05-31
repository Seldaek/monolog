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

use Monolog\Formatter\SimpleFormatter;
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
     * @param string $ident
     * @param mixed $facility
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($ident, $facility = LOG_USER, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        if (false === strpos(PHP_OS, 'WIN')) {
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

        if (!openlog($ident, LOG_PID, $facility)) {
            throw new \LogicException('Can\'t open syslog for ident "'.$ident.'" and facility "'.$facility.'"');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        closelog();
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        syslog($this->logLevels[$record['level']], (string) $record['formatted']);
    }
}
