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
use PHPUnit_Framework_Error_Deprecated;

/**
 * @covers Monolog\Handler\RotatingFileHandler
 */
class RotatingFileHandlerTest extends TestCase
{
    public function setUp()
    {
        $dir = __DIR__.'/Fixtures';
        chmod($dir, 0777);
        if (!is_writable($dir)) {
            $this->markTestSkipped($dir.' must be writable to test the RotatingFileHandler.');
        }
    }

    public function testRotationCreatesNewFile()
    {
        touch(__DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400).'.rot');

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot');
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord());

        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        $this->assertTrue(file_exists($log));
        $this->assertEquals('test', file_get_contents($log));
    }

    /**
     * @dataProvider rotationTests
     */
    public function testRotation($createFile, $dateFormat, $timeCallback)
    {
        touch($old1 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-1)).'.rot');
        touch($old2 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-2)).'.rot');
        touch($old3 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-3)).'.rot');
        touch($old4 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-4)).'.rot');

        $log = __DIR__.'/Fixtures/foo-'.date($dateFormat).'.rot';

        if ($createFile) {
            touch($log);
        }

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->setFilenameFormat('{filename}-{date}', $dateFormat);
        $handler->handle($this->getRecord());

        $handler->close();

        $this->assertTrue(file_exists($log));
        $this->assertTrue(file_exists($old1));
        $this->assertEquals($createFile, file_exists($old2));
        $this->assertEquals($createFile, file_exists($old3));
        $this->assertEquals($createFile, file_exists($old4));
        $this->assertEquals('test', file_get_contents($log));
    }

    public function rotationTests()
    {
        $now = time();
        $dayCallback = function($ago) use ($now) {
            return $now + 86400 * $ago;
        };
        $monthCallback = function($ago) {
            return gmmktime(0, 0, 0, date('n') + $ago, date('d'), date('Y'));
        };
        $yearCallback = function($ago) {
            return gmmktime(0, 0, 0, date('n'), date('d'), date('Y') + $ago);
        };

        return array(
            'Rotation is triggered when the file of the current day is not present'
                => array(true, RotatingFileHandler::FILE_PER_DAY, $dayCallback),
            'Rotation is not triggered when the file of the current day is already present'
                => array(false, RotatingFileHandler::FILE_PER_DAY, $dayCallback),

            'Rotation is triggered when the file of the current month is not present'
                => array(true, RotatingFileHandler::FILE_PER_MONTH, $monthCallback),
            'Rotation is not triggered when the file of the current month is already present'
                => array(false, RotatingFileHandler::FILE_PER_MONTH, $monthCallback),

            'Rotation is triggered when the file of the current year is not present'
                => array(true, RotatingFileHandler::FILE_PER_YEAR, $yearCallback),
            'Rotation is not triggered when the file of the current year is already present'
                => array(false, RotatingFileHandler::FILE_PER_YEAR, $yearCallback),
        );
    }

    /**
     * @dataProvider dateFormatProvider
     */
    public function testAllowOnlyFixedDefinedDateFormats($dateFormat, $valid)
    {
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        if (!$valid) {
            $this->setExpectedExceptionRegExp(PHPUnit_Framework_Error_Deprecated::class, '~^Invalid date format~');
        }
        $handler->setFilenameFormat('{filename}-{date}', $dateFormat);
    }

    public function dateFormatProvider()
    {
        return array(
            array(RotatingFileHandler::FILE_PER_DAY, true),
            array(RotatingFileHandler::FILE_PER_MONTH, true),
            array(RotatingFileHandler::FILE_PER_YEAR, true),
            array('m-d-Y', false),
            array('Y-m-d-h-i', false)
        );
    }

    /**
     * @dataProvider filenameFormatProvider
     */
    public function testDisallowFilenameFormatsWithoutDate($filenameFormat, $valid)
    {
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        if (!$valid) {
            $this->setExpectedExceptionRegExp(PHPUnit_Framework_Error_Deprecated::class, '~^Invalid filename format~');
        }

        $handler->setFilenameFormat($filenameFormat, RotatingFileHandler::FILE_PER_DAY);
    }

    public function filenameFormatProvider()
    {
        return array(
            array('{filename}', false),
            array('{filename}-{date}', true),
            array('{date}', true),
            array('foobar-{date}', true),
            array('foo-{date}-bar', true),
            array('{date}-foobar', true),
            array('foobar', false),
        );
    }

    public function testReuseCurrentFile()
    {
        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        file_put_contents($log, "foo");
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot');
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord());
        $this->assertEquals('footest', file_get_contents($log));
    }

    public function tearDown()
    {
        foreach (glob(__DIR__.'/Fixtures/*.rot') as $file) {
            unlink($file);
        }
    }
}
