<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Test;

use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\DateTimeImmutable;
use Monolog\Formatter\FormatterInterface;

/**
 * Lets you easily generate log records and a dummy formatter for testing purposes
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Level from \Monolog\Logger
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param mixed[] $context
     *
     * @phpstan-param  Level $level
     */
    protected function getRecord(int $level = Logger::WARNING, string|\Stringable $message = 'test', array $context = [], string $channel = 'test', \DateTimeImmutable $datetime = new DateTimeImmutable(true), array $extra = []): LogRecord
    {
        return new LogRecord(
            message: (string) $message,
            context: $context,
            level: $level,
            channel: $channel,
            datetime: $datetime,
            extra: $extra,
        );
    }

    /**
     * @phpstan-return list<LogRecord>
     */
    protected function getMultipleRecords(): array
    {
        return [
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information'),
            $this->getRecord(Logger::WARNING, 'warning'),
            $this->getRecord(Logger::ERROR, 'error'),
        ];
    }

    protected function getIdentityFormatter(): FormatterInterface
    {
        $formatter = $this->createMock(FormatterInterface::class);
        $formatter->expects($this->any())
            ->method('format')
            ->will($this->returnCallback(function ($record) {
                return $record->message;
            }));

        return $formatter;
    }
}
