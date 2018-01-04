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
use Monolog\Test\TestCase;

class FluentdFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\FluentdFormatter::__construct
     * @covers Monolog\Formatter\FluentdFormatter::isUsingLevelsInTag
     */
    public function testConstruct()
    {
        $formatter = new FluentdFormatter();
        $this->assertFalse($formatter->isUsingLevelsInTag());
        $formatter = new FluentdFormatter(false);
        $this->assertFalse($formatter->isUsingLevelsInTag());
        $formatter = new FluentdFormatter(true);
        $this->assertTrue($formatter->isUsingLevelsInTag());
    }

    /**
     * @covers Monolog\Formatter\FluentdFormatter::format
     */
    public function testFormat()
    {
        $record = $this->getRecord(Logger::WARNING);
        $record['datetime'] = new \DateTimeImmutable("@0");

        $formatter = new FluentdFormatter();
        $this->assertSame(
            '["test",0,{"message":"test","extra":[],"level":300,"level_name":"WARNING"}]',
            $formatter->format($record)
        );
    }

    /**
     * @covers Monolog\Formatter\FluentdFormatter::format
     */
    public function testFormatWithTag()
    {
        $record = $this->getRecord(Logger::ERROR);
        $record['datetime'] = new \DateTimeImmutable("@0");

        $formatter = new FluentdFormatter(true);
        $this->assertSame(
            '["test.error",0,{"message":"test","extra":[]}]',
            $formatter->format($record)
        );
    }
}
