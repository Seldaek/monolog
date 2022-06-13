<?php

namespace Monolog\Handler\Syslog;

use Monolog\Level;

class SyslogUtils
{
    public static function toSyslogPriority(Level $level): int
    {
        return match ($level) {
            Level::Debug     => \LOG_DEBUG,
            Level::Info      => \LOG_INFO,
            Level::Notice    => \LOG_NOTICE,
            Level::Warning   => \LOG_WARNING,
            Level::Error     => \LOG_ERR,
            Level::Critical  => \LOG_CRIT,
            Level::Alert     => \LOG_ALERT,
            Level::Emergency => \LOG_EMERG,
        };
    }
}
