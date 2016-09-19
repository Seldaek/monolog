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

use Monolog\Test\TestCase;
use Monolog\Logger;
use Monolog\Util\LocalSocket;

/**
 * @author Robert Kaufmann III <rok3@rok3.me>
 */
class LogEntriesHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    /**
     * @var LogEntriesHandler
     */
    private $handler;

    public function testWriteContent()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Critical write test'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] test.CRITICAL: Critical write test/', $content);
    }

    public function testWriteBatchContent()
    {
        $records = [
            $this->getRecord(),
            $this->getRecord(),
            $this->getRecord(),
        ];
        $this->initHandlerAndSocket();
        $this->handler->handleBatch($records);

        $content = $this->socket->getOutput();
        $this->assertRegexp('/(testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] .* \[\] \[\]\n){3}/', $content);
    }

    private function initHandlerAndSocket()
    {
        $this->socket = LocalSocket::initSocket();

        $useSSL = extension_loaded('openssl');
        $this->handler = new LogEntriesHandler('testToken', $useSSL, Logger::DEBUG, true);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');
    }

    public function tearDown()
    {
        unset($this->socket, $this->handler);
    }
}
