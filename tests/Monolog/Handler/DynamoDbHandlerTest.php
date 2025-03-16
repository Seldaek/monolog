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

use Aws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\MockObject\MockObject;

class DynamoDbHandlerTest extends \Monolog\Test\MonologTestCase
{
    private DynamoDbClient&MockObject $client;

    private bool $isV3;

    public function setUp(): void
    {
        if (!class_exists(DynamoDbClient::class)) {
            $this->markTestSkipped('aws/aws-sdk-php not installed');
        }

        $this->isV3 = \defined('Aws\Sdk::VERSION') && version_compare(\Aws\Sdk::VERSION, '3.0', '>=');

        $implementedMethods = ['__call'];

        $clientMockBuilder = $this->getMockBuilder(DynamoDbClient::class)
            ->onlyMethods($implementedMethods)
            ->disableOriginalConstructor();

        $this->client = $clientMockBuilder->getMock();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->client);
    }

    public function testGetFormatter()
    {
        $handler = new DynamoDbHandler($this->client, 'foo');
        $this->assertInstanceOf('Monolog\Formatter\ScalarFormatter', $handler->getFormatter());
    }

    public function testHandle()
    {
        $record = $this->getRecord();
        $formatter = $this->createMock('Monolog\Formatter\FormatterInterface');
        $formatted = ['foo' => 1, 'bar' => 2];
        $handler = new DynamoDbHandler($this->client, 'foo');
        $handler->setFormatter($formatter);

        if ($this->isV3) {
            $expFormatted = ['foo' => ['N' => 1], 'bar' => ['N' => 2]];
        } else {
            $expFormatted = $formatted;
        }

        $formatter
             ->expects($this->once())
             ->method('format')
             ->with($record)
             ->willReturn($formatted);
        $this->client
             ->expects($this->once())
             ->method('__call')
             ->with('putItem', [[
                 'TableName' => 'foo',
                 'Item' => $expFormatted,
             ]]);

        $handler->handle($record);
    }
}
