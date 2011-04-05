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

use Monolog\Logger;

class RotatingFileHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $dir = __DIR__.'/Fixtures';
        chmod($dir, 0777);
        if (!is_writable($dir)) {
            $this->markTestSkipped($dir.' must be writeable to test the RotatingFileHandler.');
        }
    }

    public function testRotationCreatesNewFile()
    {
        touch(__DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400).'.rot');

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot');
        $handler->write(array('message' => 'test'));

        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        $this->assertTrue(file_exists($log));
        $this->assertEquals('test', file_get_contents($log));
    }

    public function testRotationDeletesOldFiles()
    {
        touch($old1 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400).'.rot');
        touch($old2 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400 * 2).'.rot');
        touch($old3 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400 * 3).'.rot');
        touch($old4 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400 * 4).'.rot');

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        $handler->write(array('message' => 'test'));
        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';

        $handler->close();

        $this->assertTrue(file_exists($log));
        $this->assertTrue(file_exists($old1));
        $this->assertFalse(file_exists($old2));
        $this->assertFalse(file_exists($old3));
        $this->assertFalse(file_exists($old4));
        $this->assertEquals('test', file_get_contents($log));
    }

    public function testRotationNotTriggeredTwiceTheSameDay()
    {
        touch($old1 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400).'.rot');
        touch($old2 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400 * 2).'.rot');
        touch($old3 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400 * 3).'.rot');
        touch($old4 = __DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400 * 4).'.rot');
        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        file_put_contents($log, 'test');

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        $handler->write(array('message' => 'test'));

        $handler->close();

        $this->assertTrue(file_exists($log));
        $this->assertTrue(file_exists($old1));
        $this->assertTrue(file_exists($old2));
        $this->assertTrue(file_exists($old3));
        $this->assertTrue(file_exists($old4));
        $this->assertEquals('testtest', file_get_contents($log));
    }

    public function testReuseCurrentFile()
    {
        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        file_put_contents($log, "foo");
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot');
        $handler->write(array('message' => 'test'));
        $this->assertEquals('footest', file_get_contents($log));
    }

    public function tearDown()
    {
        foreach (glob(__DIR__.'/Fixtures/*.rot') as $file) {
            unlink($file);
        }
    }
}
