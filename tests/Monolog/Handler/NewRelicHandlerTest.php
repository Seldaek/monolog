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
use Psr\Log\LogLevel;

class NewRelicHandlerTest extends TestCase
{
    public static $appname;

    public function setUp()
    {
        $this::$appname = null;
    }

    /**
     * @expectedException Monolog\Handler\MissingExtensionException
     */
    public function testThehandlerThrowsAnExceptionIfTheNRExtensionIsNotLoaded()
    {
        $handler = new StubNewRelicHandlerWithoutExtension();
        $handler->handle($this->getRecord(Logger::ERROR));
    }

    public function testThehandlerCanHandleTheRecord()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Logger::ERROR));
    }

    public function testThehandlerCanAddParamsToTheNewRelicTrace()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Logger::ERROR, 'log message', array('a' => 'b')));
    }

    public function testTheAppNameIsNullByDefault()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Logger::ERROR, 'log message'));

        $this->assertEquals(null, $this::$appname);
    }

    public function testTheAppNameCanBeInjectedFromtheConstructor()
    {
        $handler = new StubNewRelicHandler(LogLevel::ALERT, false, 'myAppName');
        $handler->handle($this->getRecord(Logger::ERROR, 'log message'));

        $this->assertEquals('myAppName', $this::$appname);
    }

    public function testTheAppNameCanBeOverriddenFromEachLog()
    {
        $handler = new StubNewRelicHandler(LogLevel::ALERT, false, 'myAppName');
        $handler->handle($this->getRecord(Logger::ERROR, 'log message', array('appname' => 'logAppName')));

        $this->assertEquals('logAppName', $this::$appname);
    }
}

class StubNewRelicHandlerWithoutExtension extends NewRelicHandler
{
    protected function isNewRelicEnabled()
    {
        return false;
    }
}

class StubNewRelicHandler extends NewRelicHandler
{
    protected function isNewRelicEnabled()
    {
        return true;
    }
}

function newrelic_notice_error()
{
    return true;
}

function newrelic_set_appname($appname)
{
    return NewRelicHandlerTest::$appname = $appname;
}

function newrelic_add_custom_parameter()
{
    return true;
}
