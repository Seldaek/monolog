<?php

namespace Monolog;

use Psr\Log\LogLevel;

/**
 * @see LevelName
 */
enum Level: int
{
    /**
     * Detailed debug information
     */
    case Debug = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    case Info = 200;

    /**
     * Uncommon events
     */
    case Notice = 250;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    case Warning = 300;

    /**
     * Runtime errors
     */
    case Error = 400;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    case Critical = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    case Alert = 550;

    /**
     * Urgent alert.
     */
    case Emergency = 600;

    public static function fromLevelName(LevelName $name): self
    {
        return match ($name) {
            LevelName::Debug => self::Debug,
            LevelName::Info => self::Info,
            LevelName::Notice => self::Notice,
            LevelName::Warning => self::Warning,
            LevelName::Error => self::Error,
            LevelName::Critical => self::Critical,
            LevelName::Alert => self::Alert,
            LevelName::Emergency => self::Emergency,
        };
    }

    /**
     * Returns true if the passed $level is higher or equal to $this
     */
    public function includes(Level $level): bool
    {
        return $this->value <= $level->value;
    }

    public function isHigherThan(Level $level): bool
    {
        return $this->value > $level->value;
    }

    public function isLowerThan(Level $level): bool
    {
        return $this->value < $level->value;
    }

    public function toLevelName(): LevelName
    {
        return LevelName::fromLevel($this);
    }

    /**
     * @return string
     * @phpstan-return \Psr\Log\LogLevel::*
     */
    public function toPsrLogLevel(): string
    {
        return match ($this) {
            self::Debug => LogLevel::DEBUG,
            self::Info => LogLevel::INFO,
            self::Notice => LogLevel::NOTICE,
            self::Warning => LogLevel::WARNING,
            self::Error => LogLevel::ERROR,
            self::Critical => LogLevel::CRITICAL,
            self::Alert => LogLevel::ALERT,
            self::Emergency => LogLevel::EMERGENCY,
        };
    }

    public const VALUES = [
        100,
        200,
        250,
        300,
        400,
        500,
        550,
        600,
    ];
}
