<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Test\TestCase;

/**
 * @coversDefaultClass \Monolog\Handler\FleepHookHandler
 */
class FleepHookHandlerTest extends TestCase
{
    /**
     * Default token to use in tests
     */
    const TOKEN = '123abc';

    private FleepHookHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl extension to run');
        }

        // Create instances of the handler and logger for convenience
        $this->handler = new FleepHookHandler(self::TOKEN);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorSetsExpectedDefaults()
    {
        $this->assertEquals(Level::Debug, $this->handler->getLevel());
        $this->assertEquals(true, $this->handler->getBubble());
    }

    /**
     * @covers ::getDefaultFormatter
     */
    public function testHandlerUsesLineFormatterWhichIgnoresEmptyArrays()
    {
        $record = $this->getRecord(Level::Debug, 'msg');

        $expectedFormatter = new LineFormatter(null, null, true, true);
        $expected = $expectedFormatter->format($record);

        $handlerFormatter = $this->handler->getFormatter();
        $actual = $handlerFormatter->format($record);

        $this->assertEquals($expected, $actual, 'Empty context and extra arrays should not be rendered');
    }

    /**
     * @covers ::__construct
     */
    public function testConnectionStringisConstructedCorrectly()
    {
        $this->assertEquals('ssl://fleep.io:443', $this->handler->getConnectionString());
    }
}
