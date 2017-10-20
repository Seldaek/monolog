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

class ChromePHPFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Monolog\Formatter\ChromePHPFormatter::format
     */
    public function testDefaultFormat()
    {
        $formatter = new ChromePHPFormatter();
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger'],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => ['ip' => '127.0.0.1'],
            'message' => 'log',
        ];

        $message = $formatter->format($record);

        $this->assertEquals(
            [
                'meh',
                [
                    'message' => 'log',
                    'context' => ['from' => 'logger'],
                    'extra' => ['ip' => '127.0.0.1'],
                ],
                'unknown',
                'error',
            ],
            $message
        );
    }

    /**
     * @covers Monolog\Formatter\ChromePHPFormatter::format
     */
    public function testFormatWithFileAndLine()
    {
        $formatter = new ChromePHPFormatter();
        $record = [
            'level' => Logger::CRITICAL,
            'level_name' => 'CRITICAL',
            'channel' => 'meh',
            'context' => ['from' => 'logger'],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => ['ip' => '127.0.0.1', 'file' => 'test', 'line' => 14],
            'message' => 'log',
        ];

        $message = $formatter->format($record);

        $this->assertEquals(
            [
                'meh',
                [
                    'message' => 'log',
                    'context' => ['from' => 'logger'],
                    'extra' => ['ip' => '127.0.0.1'],
                ],
                'test : 14',
                'error',
            ],
            $message
        );
    }

    /**
     * @covers Monolog\Formatter\ChromePHPFormatter::format
     */
    public function testFormatWithoutContext()
    {
        $formatter = new ChromePHPFormatter();
        $record = [
            'level' => Logger::DEBUG,
            'level_name' => 'DEBUG',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        $message = $formatter->format($record);

        $this->assertEquals(
            [
                'meh',
                'log',
                'unknown',
                'log',
            ],
            $message
        );
    }

    /**
     * @covers Monolog\Formatter\ChromePHPFormatter::formatBatch
     */
    public function testBatchFormatThrowException()
    {
        $formatter = new ChromePHPFormatter();
        $records = [
            [
                'level' => Logger::INFO,
                'level_name' => 'INFO',
                'channel' => 'meh',
                'context' => [],
                'datetime' => new \DateTimeImmutable("@0"),
                'extra' => [],
                'message' => 'log',
            ],
            [
                'level' => Logger::WARNING,
                'level_name' => 'WARNING',
                'channel' => 'foo',
                'context' => [],
                'datetime' => new \DateTimeImmutable("@0"),
                'extra' => [],
                'message' => 'log2',
            ],
        ];

        $this->assertEquals(
            [
                [
                    'meh',
                    'log',
                    'unknown',
                    'info',
                ],
                [
                    'foo',
                    'log2',
                    'unknown',
                    'warn',
                ],
            ],
            $formatter->formatBatch($records)
        );
    }
}
