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

/**
 * @covers Monolog\Handler\ChromePHPHandler
 */
class ChromePHPHandlerTest extends TestCase
{
    protected function setUp()
    {
        TestChromePHPHandler::reset();
    }

    public function testHeaders()
    {
        $handler = new TestChromePHPHandler();
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::WARNING));

        $expected = array(
            'X-ChromePhp-Data'   => base64_encode(utf8_encode(json_encode(array(
                'version' => ChromePHPHandler::VERSION,
                'columns' => array('label', 'log', 'backtrace', 'type'),
                'rows' => array(
                    'test',
                    'test',
                ),
                'request_uri' => '',
            ))))
        );

        $this->assertEquals($expected, $handler->getHeaders());
    }

    public function testConcurrentHandlers()
    {
        $handler = new TestChromePHPHandler();
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::WARNING));

        $handler2 = new TestChromePHPHandler();
        $handler2->setFormatter($this->getIdentityFormatter());
        $handler2->handle($this->getRecord(Logger::DEBUG));
        $handler2->handle($this->getRecord(Logger::WARNING));

        $expected = array(
            'X-ChromePhp-Data'   => base64_encode(utf8_encode(json_encode(array(
                'version' => ChromePHPHandler::VERSION,
                'columns' => array('label', 'log', 'backtrace', 'type'),
                'rows' => array(
                    'test',
                    'test',
                    'test',
                    'test',
                ),
                'request_uri' => '',
            ))))
        );

        $this->assertEquals($expected, $handler2->getHeaders());
    }
}

class TestChromePHPHandler extends ChromePHPHandler
{
    protected $headers = array();

    public static function reset()
    {
        self::$initialized = false;
        self::$json['rows'] = array();
    }

    protected function sendHeader($header, $content)
    {
        $this->headers[$header] = $content;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
