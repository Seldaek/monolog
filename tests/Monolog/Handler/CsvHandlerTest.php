<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\TestCase;
use Monolog\Logger;

class CsvHandlerTest extends TestCase
{
    /**
     * @covers CsvHandler::write
     */
    public function testWrite()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new CsvHandler($handle);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord(Logger::WARNING, 'test'));
        $handler->handle($this->getRecord(Logger::WARNING, 'test2'));
        $handler->handle($this->getRecord(Logger::WARNING, 'test3'));
        fseek($handle, 0);
        $this->assertEquals("test\ntest2\ntest3\n", fread($handle, 100));
    }

    /**
     * @covers CsvHandler::write
     */
    public function testWriteWithNormalizer()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new CsvHandler($handle);
        $handler->setFormatter($this->getNormalizeFormatter());
        $handler->handle($this->getRecord(Logger::WARNING, 'doesn\'t fail'));
        fseek($handle, 0);
        $regexp = "~\\A'doesn''t fail',Array,300,WARNING,test,'[0-9]{4}\\-[0-9]{2}+\\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}',Array\n\\Z~";
        $this->assertSame(1, preg_match($regexp, fread($handle, 100)));
    }

    /**
     * @return \Monolog\Formatter\NormalizerFormatter
     */
    protected function getNormalizeFormatter()
    {
        return $this->getMock('Monolog\\Formatter\\NormalizerFormatter', null);
    }
}
