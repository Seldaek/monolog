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

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Manager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('mongodb')]
class MongoDBHandlerTest extends \Monolog\Test\MonologTestCase
{
    public function testConstructorShouldThrowExceptionForInvalidMongo()
    {
        $this->expectException(\TypeError::class);

        new MongoDBHandler(new \stdClass, 'db', 'collection');
    }

    public function testHandleWithLibraryClient()
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('mongodb/mongodb not installed');
        }

        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $client->expects($this->once())
            ->method('getCollection')
            ->with('db', 'collection')
            ->willReturn($collection);

        $record = $this->getRecord();
        $expected = $record->toArray();
        $expected['datetime'] = new UTCDateTime((int) floor(((float) $record->datetime->format('U.u')) * 1000));

        $collection->expects($this->once())
            ->method('insertOne')
            ->with($expected);

        $handler = new MongoDBHandler($client, 'db', 'collection');
        $handler->handle($record);
    }

    public function testHandleWithDriverManager()
    {
        $manager = new Manager('mongodb://localhost:27017');
        $handler = new MongoDBHandler($manager, 'test', 'monolog');
        $record = $this->getRecord();

        try {
            $handler->handle($record);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Could not connect to MongoDB server on mongodb://localhost:27017');
        }
    }
}
