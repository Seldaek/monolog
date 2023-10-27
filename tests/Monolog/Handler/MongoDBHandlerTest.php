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

use MongoDB\Driver\Manager;
use Monolog\Test\TestCase;

/**
 * @requires extension mongodb
 */
class MongoDBHandlerTest extends TestCase
{
    public function testConstructorShouldThrowExceptionForInvalidMongo()
    {
        $this->expectException(\TypeError::class);

        new MongoDBHandler(new \stdClass, 'db', 'collection');
    }

    public function testHandleWithLibraryClient()
    {
        if (!(class_exists('MongoDB\Client'))) {
            $this->markTestSkipped('mongodb/mongodb not installed');
        }

        $mongodb = $this->getMockBuilder('MongoDB\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $collection = $this->getMockBuilder('MongoDB\Collection')
            ->disableOriginalConstructor()
            ->getMock();

        $mongodb->expects($this->once())
            ->method('selectCollection')
            ->with('db', 'collection')
            ->will($this->returnValue($collection));

        $record = $this->getRecord();
        $expected = $record->toArray();
        $expected['datetime'] = new \MongoDB\BSON\UTCDateTime((int) floor(((float) $record->datetime->format('U.u')) * 1000));

        $collection->expects($this->once())
            ->method('insertOne')
            ->with($expected);

        $handler = new MongoDBHandler($mongodb, 'db', 'collection');
        $handler->handle($record);
    }

    public function testHandleWithDriverManager()
    {
        /* This can become a unit test once ManagerInterface can be mocked.
         * See: https://jira.mongodb.org/browse/PHPC-378
         */
        $mongodb = new Manager('mongodb://localhost:27017');
        $handler = new MongoDBHandler($mongodb, 'test', 'monolog');
        $record = $this->getRecord();

        try {
            $handler->handle($record);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Could not connect to MongoDB server on mongodb://localhost:27017');
        }
    }
}
