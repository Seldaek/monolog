<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use Psr\Log\LogLevel;

/**
 * Represents the log levels
 *
 * Monolog supports the logging levels described by RFC 5424 {@see https://datatracker.ietf.org/doc/html/rfc5424}
 * but due to BC the severity values used internally are not 0-7.
 *
 * To get the level name out of a Level there are three options:
 *
 * - Use ->getName() to get the standard Monolog name which is full uppercased (e.g. "DEBUG")
 * - Use ->toPsrLogLevel() to get the standard PSR-3 name which is full lowercased (e.g. "debug")
 * - Use ->name to get the enum case's name which is capitalized (e.g. "Debug")
 *
 * To get the value for filtering, if the includes/isLowerThan/isHigherThan methods are
 * not enough, you can use ->value to get the enum case's integer value.
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

    /**
     * @param value-of<self::NAMES>|LogLevel::*|'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency' $name
     * @return static
     */
    public static function fromName(string $name): self
    {
        return match ($name) {
            'debug', 'Debug', 'DEBUG' => self::Debug,
            'info', 'Info', 'INFO' => self::Info,
            'notice', 'Notice', 'NOTICE' => self::Notice,
            'warning', 'Warning', 'WARNING' => self::Warning,
            'error', 'Error', 'ERROR' => self::Error,
            'critical', 'Critical', 'CRITICAL' => self::Critical,
            'alert', 'Alert', 'ALERT' => self::Alert,
            'emergency', 'Emergency', 'EMERGENCY' => self::Emergency,
        };
    }

    /**
     * @param value-of<self::VALUES> $value
     * @return static
     */
    public static function fromValue(int $value): self
    {
        return self::from($value);
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

    /**
     * Returns the monolog standardized all-capitals name of the level
     *
     * Use this instead of $level->name which returns the enum case name (e.g. Debug vs DEBUG if you use getName())
     *
     * @return value-of<self::NAMES>
     */
    public function getName(): string
    {
        return match ($this) {
            self::Debug => 'DEBUG',
            self::Info => 'INFO',
            self::Notice => 'NOTICE',
            self::Warning => 'WARNING',
            self::Error => 'ERROR',
            self::Critical => 'CRITICAL',
            self::Alert => 'ALERT',
            self::Emergency => 'EMERGENCY',
        };
    }

    /**
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

    public const NAMES = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
        'EMERGENCY',
    ];
}
