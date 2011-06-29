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

class JsonFormatterTest extends TestCase
{
    public function testFormat()
    {
        $formatter = new JsonFormatter();
        $record = $this->getRecord();
        $this->assertEquals(json_encode($record), $formatter->format($record));
    }

    public function testFormatBatch()
    {
        $formatter = new JsonFormatter();
        $records = array(
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        );
        $this->assertEquals(json_encode($records), $formatter->formatBatch($records));
    }
}
