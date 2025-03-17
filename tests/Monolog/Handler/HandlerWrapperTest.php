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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Alexey Karapetov <alexey@karapetov.com>
 */
class HandlerWrapperTest extends \Monolog\Test\MonologTestCase
{
    private HandlerWrapper $wrapper;

    private HandlerInterface&MockObject $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->createMock(HandlerInterface::class);
        $this->wrapper = new HandlerWrapper($this->handler);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->wrapper);
        unset($this->handler);
    }

    public static function trueFalseDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testIsHandling(bool $result)
    {
        $record = $this->getRecord();
        $this->handler->expects($this->once())
            ->method('isHandling')
            ->with($record)
            ->willReturn($result);

        $this->assertEquals($result, $this->wrapper->isHandling($record));
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testHandle(bool $result)
    {
        $record = $this->getRecord();
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($record)
            ->willReturn($result);

        $this->assertEquals($result, $this->wrapper->handle($record));
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testHandleBatch(bool $result)
    {
        $records = $this->getMultipleRecords();
        $this->handler->expects($this->once())
            ->method('handleBatch')
            ->with($records);

        $this->wrapper->handleBatch($records);
    }
}
