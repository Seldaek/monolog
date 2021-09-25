<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;

/**
 * Serializes a log message according to RFC 5424
 *
 * @author Dalibor Karlović <dalibor.karlovic@sigwin.hr>
 */
class SyslogFormatter extends LineFormatter
{
    private const SYSLOG_FACILITY_USER = 1;
    private const LEVELS = [
        Logger::EMERGENCY => 0,
        Logger::ALERT => 1,
        Logger::CRITICAL => 2,
        Logger::ERROR => 3,
        Logger::WARNING => 4,
        Logger::NOTICE => 5,
        Logger::INFO => 6,
        Logger::DEBUG => 7,
    ];
    // [%datetime%] %channel%.%level_name%: %message% %context% %extra%
    private const FORMAT = '<%extra.priority%>1 %datetime% %extra.hostname% %channel% %extra.procid% %channel% %extra.structured-data% %message%';
    private const NILVALUE = '-';

    public function __construct()
    {
        parent::__construct(self::FORMAT, 'Y-m-d\TH:i:s.uP');

        $this->extra = [
            'hostname' => (string) gethostname(),
            'procid' => (int) getmypid(),
        ];
    }

    public function format(array $record): string
    {
        $extra = $this->extra;
        $extra['priority'] = self::calculatePriority($record['level']);
        $extra['structured-data'] = self::NILVALUE;
        
        $record['extra'] = $extra;
        
        return parent::format($record);
    }
    
    private static function calculatePriority(int $level): int
    {
        return (self::SYSLOG_FACILITY_USER * 8) + self::LEVELS[$level];
    }
}
