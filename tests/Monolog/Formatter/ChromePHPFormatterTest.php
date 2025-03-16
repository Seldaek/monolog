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

use Monolog\Level;
use Monolog\Test\MonologTestCase;

class ChromePHPFormatterTest extends MonologTestCase
{
    /**
     * @covers Monolog\Formatter\ChromePHPFormatter::format
     */
    public function testDefaultFormat()
    {
        $formatter = new ChromePHPFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['ip' => '127.0.0.1'],
        );

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
        $record = $this->getRecord(
            Level::Critical,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['ip' => '127.0.0.1', 'file' => 'test', 'line' => 14],
        );

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
        $record = $this->getRecord(
            Level::Debug,
            'log',
            channel: 'meh',
            datetime: new \DateTimeImmutable("@0"),
        );

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
            $this->getRecord(
                Level::Info,
                'log',
                channel: 'meh',
                datetime: new \DateTimeImmutable("@0"),
            ),
            $this->getRecord(
                Level::Warning,
                'log2',
                channel: 'foo',
                datetime: new \DateTimeImmutable("@0"),
            ),
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
