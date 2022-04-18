<?php

namespace Monolog;

/**
 * @see Level
 */
enum LevelName: string
{
    case Debug = 'DEBUG';
    case Info = 'INFO';
    case Notice = 'NOTICE';
    case Warning = 'WARNING';
    case Error = 'ERROR';
    case Critical = 'CRITICAL';
    case Alert = 'ALERT';
    case Emergency = 'EMERGENCY';

    public static function fromLevel(Level $level): self
    {
        return match ($level) {
            Level::Debug => self::Debug,
            Level::Info => self::Info,
            Level::Notice => self::Notice,
            Level::Warning => self::Warning,
            Level::Error => self::Error,
            Level::Critical => self::Critical,
            Level::Alert => self::Alert,
            Level::Emergency => self::Emergency,
        };
    }

    public function toLevel(): Level
    {
        return Level::fromLevelName($this);
    }

    public const VALUES = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
        'EMERGENCY',
    ];

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
