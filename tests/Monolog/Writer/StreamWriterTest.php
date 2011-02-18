<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Writer;

use Monolog\Logger;

class StreamWritterTest extends \PHPUnit_Framework_TestCase
{
    public function testWrite()
    {
        $handle = fopen('php://memory', 'a+');
        $writer = new StreamWriter($handle);
        $writer->write('log', array('level' => Logger::WARNING, 'message' => 'test'));
        $writer->write('log', array('level' => Logger::WARNING, 'message' => 'test2'));
        $writer->write('log', array('level' => Logger::WARNING, 'message' => 'test3'));
        fseek($handle, 0);
        $this->assertEquals('testtest2test3', fread($handle, 100));
    }

    public function testClose()
    {
        $handle = fopen('php://memory', 'a+');
        $writer = new StreamWriter($handle);
        $this->assertTrue(is_resource($handle));
        $writer->close();
        $this->assertFalse(is_resource($handle));
    }
}
