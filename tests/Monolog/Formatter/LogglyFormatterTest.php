<?php

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
use Monolog\TestCase;

class LogglyFormatterTest extends TestCase
{

    /**
     * @covers Monolog\Formatter\LogglyFormatter::formatBatch
     */
    public function testFormatBatch()
    {
        $formatter = new LogglyFormatter();
        $records = $expected = array(
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        );
        array_walk($expected, function(&$value, $key) {
            $value = json_encode($value);
        });
        $this->assertEquals(implode("\n", $expected), $formatter->formatBatch($records));
    }

}
