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

class WildfireFormatterTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Formatter\WildfireFormatter::format
     */
    public function testDefaultFormat()
    {
        $wildfire = new WildfireFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            extra: ['ip' => '127.0.0.1'],
        );

        $message = $wildfire->format($record);

        $this->assertEquals(
            '125|[{"Type":"ERROR","File":"","Line":"","Label":"meh"},'
                .'{"message":"log","context":{"from":"logger"},"extra":{"ip":"127.0.0.1"}}]|',
            $message
        );
    }

    /**
     * @covers Monolog\Formatter\WildfireFormatter::format
     */
    public function testFormatWithFileAndLine()
    {
        $wildfire = new WildfireFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            extra: ['ip' => '127.0.0.1', 'file' => 'test', 'line' => 14],
        );

        $message = $wildfire->format($record);

        $this->assertEquals(
            '129|[{"Type":"ERROR","File":"test","Line":14,"Label":"meh"},'
                .'{"message":"log","context":{"from":"logger"},"extra":{"ip":"127.0.0.1"}}]|',
            $message
        );
    }

    /**
     * @covers Monolog\Formatter\WildfireFormatter::format
     */
    public function testFormatWithoutContext()
    {
        $wildfire = new WildfireFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
        );

        $message = $wildfire->format($record);

        $this->assertEquals(
            '58|[{"Type":"ERROR","File":"","Line":"","Label":"meh"},"log"]|',
            $message
        );
    }

    /**
     * @covers Monolog\Formatter\WildfireFormatter::formatBatch
     */
    public function testBatchFormatThrowException()
    {
        $this->expectException(\BadMethodCallException::class);

        $wildfire = new WildfireFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
        );

        $wildfire->formatBatch([$record]);
    }

    /**
     * @covers Monolog\Formatter\WildfireFormatter::format
     */
    public function testTableFormat()
    {
        $wildfire = new WildfireFormatter();
        $record = $this->getRecord(
            Level::Error,
            'table-message',
            channel: 'table-channel',
            context: [
                'table' => [
                    ['col1', 'col2', 'col3'],
                    ['val1', 'val2', 'val3'],
                    ['foo1', 'foo2', 'foo3'],
                    ['bar1', 'bar2', 'bar3'],
                ],
            ],
        );

        $message = $wildfire->format($record);

        $this->assertEquals(
            '171|[{"Type":"TABLE","File":"","Line":"","Label":"table-channel: table-message"},[["col1","col2","col3"],["val1","val2","val3"],["foo1","foo2","foo3"],["bar1","bar2","bar3"]]]|',
            $message
        );
    }
}
