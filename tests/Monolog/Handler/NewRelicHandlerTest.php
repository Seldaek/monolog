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
use Monolog\Test\TestCase;
use Monolog\Level;

class NewRelicHandlerTest extends TestCase
{
    public static $appname;
    public static $customParameters;
    public static $transactionName;

    public function setUp(): void
    {
        self::$appname = null;
        self::$customParameters = [];
        self::$transactionName = null;
    }

    public function testThehandlerThrowsAnExceptionIfTheNRExtensionIsNotLoaded()
    {
        $handler = new StubNewRelicHandlerWithoutExtension();

        $this->expectException(MissingExtensionException::class);

        $handler->handle($this->getRecord(Level::Error));
    }

    public function testThehandlerCanHandleTheRecord()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Level::Error));
    }

    public function testThehandlerCanAddContextParamsToTheNewRelicTrace()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Level::Error, 'log message', ['a' => 'b']));
        $this->assertEquals(['context_a' => 'b'], self::$customParameters);
    }

    public function testThehandlerCanAddExplodedContextParamsToTheNewRelicTrace()
    {
        $handler = new StubNewRelicHandler(Level::Error, true, self::$appname, true);
        $handler->handle($this->getRecord(
            Level::Error,
            'log message',
            ['a' => ['key1' => 'value1', 'key2' => 'value2']]
        ));
        $this->assertEquals(
            ['context_a_key1' => 'value1', 'context_a_key2' => 'value2'],
            self::$customParameters
        );
    }

    public function testThehandlerCanAddExtraParamsToTheNewRelicTrace()
    {
        $record = $this->getRecord(Level::Error, 'log message');
        $record->extra = ['c' => 'd'];

        $handler = new StubNewRelicHandler();
        $handler->handle($record);

        $this->assertEquals(['extra_c' => 'd'], self::$customParameters);
    }

    public function testThehandlerCanAddExplodedExtraParamsToTheNewRelicTrace()
    {
        $record = $this->getRecord(Level::Error, 'log message');
        $record->extra = ['c' => ['key1' => 'value1', 'key2' => 'value2']];

        $handler = new StubNewRelicHandler(Level::Error, true, self::$appname, true);
        $handler->handle($record);

        $this->assertEquals(
            ['extra_c_key1' => 'value1', 'extra_c_key2' => 'value2'],
            self::$customParameters
        );
    }

    public function testThehandlerCanAddExtraContextAndParamsToTheNewRelicTrace()
    {
        $record = $this->getRecord(Level::Error, 'log message', ['a' => 'b']);
        $record->extra = ['c' => 'd'];

        $handler = new StubNewRelicHandler();
        $handler->handle($record);

        $expected = [
            'context_a' => 'b',
            'extra_c' => 'd',
        ];

        $this->assertEquals($expected, self::$customParameters);
    }

    public function testThehandlerCanHandleTheRecordsFormattedUsingTheLineFormatter()
    {
        $handler = new StubNewRelicHandler();
        $handler->setFormatter(new LineFormatter());
        $handler->handle($this->getRecord(Level::Error));
    }

    public function testTheAppNameIsNullByDefault()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Level::Error, 'log message'));

        $this->assertEquals(null, self::$appname);
    }

    public function testTheAppNameCanBeInjectedFromtheConstructor()
    {
        $handler = new StubNewRelicHandler(Level::Debug, false, 'myAppName');
        $handler->handle($this->getRecord(Level::Error, 'log message'));

        $this->assertEquals('myAppName', self::$appname);
    }

    public function testTheAppNameCanBeOverriddenFromEachLog()
    {
        $handler = new StubNewRelicHandler(Level::Debug, false, 'myAppName');
        $handler->handle($this->getRecord(Level::Error, 'log message', ['appname' => 'logAppName']));

        $this->assertEquals('logAppName', self::$appname);
    }

    public function testTheTransactionNameIsNullByDefault()
    {
        $handler = new StubNewRelicHandler();
        $handler->handle($this->getRecord(Level::Error, 'log message'));

        $this->assertEquals(null, self::$transactionName);
    }

    public function testTheTransactionNameCanBeInjectedFromTheConstructor()
    {
        $handler = new StubNewRelicHandler(Level::Debug, false, null, false, 'myTransaction');
        $handler->handle($this->getRecord(Level::Error, 'log message'));

        $this->assertEquals('myTransaction', self::$transactionName);
    }

    public function testTheTransactionNameCanBeOverriddenFromEachLog()
    {
        $handler = new StubNewRelicHandler(Level::Debug, false, null, false, 'myTransaction');
        $handler->handle($this->getRecord(Level::Error, 'log message', ['transaction_name' => 'logTransactName']));

        $this->assertEquals('logTransactName', self::$transactionName);
    }
}

class StubNewRelicHandlerWithoutExtension extends NewRelicHandler
{
    protected function isNewRelicEnabled(): bool
    {
        return false;
    }
}

class StubNewRelicHandler extends NewRelicHandler
{
    protected function isNewRelicEnabled(): bool
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

function newrelic_name_transaction($transactionName)
{
    return NewRelicHandlerTest::$transactionName = $transactionName;
}

function newrelic_add_custom_parameter($key, $value)
{
    NewRelicHandlerTest::$customParameters[$key] = $value;

    return true;
}
