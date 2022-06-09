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
use Monolog\Level;

class DoctrineCouchDBHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Doctrine\CouchDB\CouchDBClient')) {
            $this->markTestSkipped('The "doctrine/couchdb" package is not installed');
        }
    }

    public function testHandle()
    {
        $client = $this->getMockBuilder('Doctrine\\CouchDB\\CouchDBClient')
            ->onlyMethods(['postDocument'])
            ->disableOriginalConstructor()
            ->getMock();

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $expected = [
            'message' => 'test',
            'context' => ['data' => ['stdClass' => []], 'foo' => 34],
            'level' => Level::Warning->value,
            'level_name' => 'WARNING',
            'channel' => 'test',
            'datetime' => (string) $record->datetime,
            'extra' => [],
        ];

        $client->expects($this->once())
            ->method('postDocument')
            ->with($expected);

        $handler = new DoctrineCouchDBHandler($client);
        $handler->handle($record);
    }
}
