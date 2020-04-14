<?php declare(strict_types=1);

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Test\TestCase;

class LokiHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $record = $this->getRecord(Logger::WARNING, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new LokiHandler(
            [
                'entrypoint' => 'http://localhost:3100/',
                'context' => [],
                'labels' => [],
                'client' => [],
                'auth' => [
                    'basic' => ['user', 'password'],
                ],
            ]
        );
        $this->assertInstanceOf(LokiHandler::class, $handler);
        try {
            $handler->handle($record);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Could not connect to loki server on http://localhost:3100');
        }
    }
}
