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

class RegistryTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        Registry::clear();
    }

    /**
     * @dataProvider hasLoggerProvider
     * @covers Monolog\Registry::hasLogger
     */
    public function testHasLogger(array $loggersToAdd, array $loggersToCheck, array $expectedResult)
    {
        foreach ($loggersToAdd as $loggerToAdd) {
            Registry::addLogger($loggerToAdd);
        }
        foreach ($loggersToCheck as $index => $loggerToCheck) {
            $this->assertSame($expectedResult[$index], Registry::hasLogger($loggerToCheck));
        }
    }

    public function hasLoggerProvider()
    {
        $logger1 = new Logger('test1');
        $logger2 = new Logger('test2');
        $logger3 = new Logger('test3');

        return [
            // only instances
            [
                [$logger1],
                [$logger1, $logger2],
                [true, false],
            ],
            // only names
            [
                [$logger1],
                ['test1', 'test2'],
                [true, false],
            ],
            // mixed case
            [
                [$logger1, $logger2],
                ['test1', $logger2, 'test3', $logger3],
                [true, true, false, false],
            ],
        ];
    }

    /**
     * @covers Monolog\Registry::clear
     */
    public function testClearClears()
    {
        Registry::addLogger(new Logger('test1'), 'log');
        Registry::clear();

        $this->expectException('\InvalidArgumentException');
        Registry::getInstance('log');
    }

    /**
     * @dataProvider removedLoggerProvider
     * @covers Monolog\Registry::addLogger
     * @covers Monolog\Registry::removeLogger
     */
    public function testRemovesLogger($loggerToAdd, $remove)
    {
        Registry::addLogger($loggerToAdd);
        Registry::removeLogger($remove);

        $this->expectException('\InvalidArgumentException');
        Registry::getInstance($loggerToAdd->getName());
    }

    public function removedLoggerProvider()
    {
        $logger1 = new Logger('test1');

        return [
            [$logger1, $logger1],
            [$logger1, 'test1'],
        ];
    }

    /**
     * @covers Monolog\Registry::addLogger
     * @covers Monolog\Registry::getInstance
     * @covers Monolog\Registry::__callStatic
     */
    public function testGetsSameLogger()
    {
        $logger1 = new Logger('test1');
        $logger2 = new Logger('test2');

        Registry::addLogger($logger1, 'test1');
        Registry::addLogger($logger2);

        $this->assertSame($logger1, Registry::getInstance('test1'));
        $this->assertSame($logger2, Registry::test2());
    }

    /**
     * @covers Monolog\Registry::getInstance
     */
    public function testFailsOnNonExistentLogger()
    {
        $this->expectException(\InvalidArgumentException::class);
        Registry::getInstance('test1');
    }

    /**
     * @covers Monolog\Registry::addLogger
     */
    public function testReplacesLogger()
    {
        $log1 = new Logger('test1');
        $log2 = new Logger('test2');

        Registry::addLogger($log1, 'log');

        Registry::addLogger($log2, 'log', true);

        $this->assertSame($log2, Registry::getInstance('log'));
    }

    /**
     * @covers Monolog\Registry::addLogger
     */
    public function testFailsOnUnspecifiedReplacement()
    {
        $log1 = new Logger('test1');
        $log2 = new Logger('test2');

        Registry::addLogger($log1, 'log');

        $this->expectException(\InvalidArgumentException::class);

        Registry::addLogger($log2, 'log');
    }
}
