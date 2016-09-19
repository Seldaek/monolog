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
 * @author Julien Breux <julien.breux@gmail.com>
 */
class LogmaticHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    /**
     * @var LogmaticHandler
     */
    private $handler;

    public function testWriteContent()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Critical write test'));

        $content = $this->socket->getOutput();

        $this->assertRegexp('/testToken {"message":"Critical write test","context":\[\],"level":500,"level_name":"CRITICAL","channel":"test","datetime":"(.*)","extra":\[\],"hostname":"testHostname","appname":"testAppname"}/', $content);
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

        $this->assertRegexp('/testToken {"message":"test","context":\[\],"level":300,"level_name":"WARNING","channel":"test","datetime":"(.*)","extra":\[\],"hostname":"testHostname","appname":"testAppname"}/', $content);
    }

    private function initHandlerAndSocket()
    {
        $this->socket = LocalSocket::initSocket();

        $useSSL = extension_loaded('openssl');
        $this->handler = new LogmaticHandler('testToken', 'testHostname', 'testAppname', $useSSL, Logger::DEBUG, true);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');
    }

    public function tearDown()
    {
        unset($this->socket, $this->handler);
    }
}
