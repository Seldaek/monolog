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

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\TestCase;

/**
 * Unit tests for the FleepHookHandler
 *
 * @author Ando Roots <ando@sqroot.eu>
 * @coversDefaultClass \Monolog\Handler\FleepHookHandler
 */
class FleepHookHandlerTest extends TestCase
{
    /**
     * Default token to use in tests
     */
    const TOKEN = '123abc';

    /**
     * @var FleepHookHandler
     */
    private $handler;

    /**
     * @var Logger
     */
    private $logger;

    public function setUp()
    {
        parent::setUp();

        if (!extension_loaded('curl')) {
            $this->markTestSkipped('This test requires curl extension to run');
        }

        // Create instances of the handler and logger for convenience
        $this->handler = new FleepHookHandler(self::TOKEN);
        $this->logger = new Logger('test');
        $this->logger->pushHandler($this->handler);

    }

    /**
     * @covers ::__construct
     */
    public function testConstructorSetsExpectedDefaults()
    {
        $this->assertEquals(self::TOKEN, $this->handler->getToken());
        $this->assertEquals(Logger::DEBUG, $this->handler->getLevel());
        $this->assertEquals(true, $this->handler->getBubble());
    }

    /**
     * @covers ::write
     */
    public function testWriteSendsFormattedMessageToFleep()
    {
        $handler = $this->mockHandler(array('send'));

        $message = 'theCakeIsALie';
        $handler->expects($this->once())
            ->method('send')
            ->with(
                $this->callback(
                    function ($message) {
                        return strstr($message, 'theCakeIsALie') && strstr($message, 'channel.ALERT');
                    }
                )
            );

        $this->sendLog($handler, $message);
    }

    /**
     * @covers ::getDefaultFormatter
     */
    public function testHandlerUsesLineFormatterWhichIgnoresEmptyArrays()
    {
        $record = array(
            'message' => 'msg',
            'context' => array(),
            'level' => Logger::DEBUG,
            'level_name' => Logger::getLevelName(Logger::DEBUG),
            'channel' => 'channel',
            'datetime' => new \DateTime(),
            'extra' => array(),
        );

        $expectedFormatter = new LineFormatter(null, null, true, true);
        $expected = $expectedFormatter->format($record);

        $handlerFormatter = $this->handler->getDefaultFormatter();
        $actual = $handlerFormatter->format($record);

        $this->assertEquals($expected, $actual, 'Empty context and extra arrays should not be rendered');

    }

    /**
     * Tests that the URL to which the message is posted is of correct format
     *
     * Example: https://fleep.io/hook/mTZG6s-XRfKdNTJtpVyVaV
     * @covers ::__construct
     */
    public function testFleepEndpointUrlIsConstructedCorrectly()
    {
        $handler = $this->mockHandler(array('execCurl'));

        $token = self::TOKEN;

        // Set up expectation to execCurl: receive curlOpts array where URL is correct
        $handler->expects($this->once())
            ->method('execCurl')
            ->with(
                $this->callback(
                    function (array $curlOpts) use ($token) {
                        return $curlOpts[CURLOPT_URL] === FleepHookHandler::HOOK_ENDPOINT . $token;
                    }
                )
            );
        $this->sendLog($handler);
    }

    /**
     * Tests that the log message is added to the POST content, under the 'message' key
     *
     * @covers ::send
     */
    public function testSendAddsMessageToCurlOpts()
    {
        $handler = $this->mockHandler(array('execCurl'));
        $handler->expects($this->once())
            ->method('execCurl')
            ->with(
                $this->callback(
                    function ($curlOpts) {
                        parse_str($curlOpts[CURLOPT_POSTFIELDS], $body);
                        return isset($body['message']) && strstr($body['message'], 'msg');
                    }
                )
            );

        $this->sendLog($handler, 'msg');
    }

    /**
     * @covers ::addCurlOptions
     */
    public function testAddCurlOptionsLeavesUnspecifiedOptionsIntact()
    {
        $this->handler->addCurlOptions(array(CURLOPT_PROXY => 'http://localhost:3128'));
        $this->assertArrayHasKey(CURLOPT_POST, $this->handler->getCurlOptions(), 'addCurlOpts deleted a key!');
    }

    public function testAddCurlOptionsAddsANewCurlOption()
    {
        $proxy = 'http://localhost:3128';
        $this->handler->addCurlOptions(array(CURLOPT_PROXY => $proxy));
        $options = $this->handler->getCurlOptions();
        $this->assertEquals($options[CURLOPT_PROXY], $proxy);
    }

    /**
     * Helper method for simulating a long sending event from the logger
     *
     * @param FleepHookHandler $handler
     * @param string $message
     */
    private function sendLog(FleepHookHandler $handler, $message = 'test')
    {
        $logger = new Logger('channel');
        $logger->pushHandler($handler);
        $logger->addAlert($message);
    }

    /**
     * Helper method for constructing a new mock of FleepHookHandler
     *
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function mockHandler(array $methods)
    {
        return $this->getMock('Monolog\Handler\FleepHookHandler', $methods, array(self::TOKEN));
    }

}
