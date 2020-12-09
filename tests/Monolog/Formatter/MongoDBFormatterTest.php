<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;

/**
 * @author Florian Plattner <me@florianplattner.de>
 */
class MongoDBFormatterTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        if (!class_exists('MongoDB\BSON\UTCDateTime')) {
            $this->markTestSkipped('ext-mongodb not installed');
        }
    }

    public function constructArgumentProvider()
    {
        return [
            [1, true, 1, true],
            [0, false, 0, false],
        ];
    }

    /**
     * @param $traceDepth
     * @param $traceAsString
     * @param $expectedTraceDepth
     * @param $expectedTraceAsString
     *
     * @dataProvider constructArgumentProvider
     */
    public function testConstruct($traceDepth, $traceAsString, $expectedTraceDepth, $expectedTraceAsString)
    {
        $formatter = new MongoDBFormatter($traceDepth, $traceAsString);

        $reflTrace = new \ReflectionProperty($formatter, 'exceptionTraceAsString');
        $reflTrace->setAccessible(true);
        $this->assertEquals($expectedTraceAsString, $reflTrace->getValue($formatter));

        $reflDepth = new \ReflectionProperty($formatter, 'maxNestingLevel');
        $reflDepth->setAccessible(true);
        $this->assertEquals($expectedTraceDepth, $reflDepth->getValue($formatter));
    }

    public function testSimpleFormat()
    {
        $record = [
            'message' => 'some log message',
            'context' => [],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable('2016-01-21T21:11:30.123456+00:00'),
            'extra' => [],
        ];

        $formatter = new MongoDBFormatter();
        $formattedRecord = $formatter->format($record);

        $this->assertCount(7, $formattedRecord);
        $this->assertEquals('some log message', $formattedRecord['message']);
        $this->assertEquals([], $formattedRecord['context']);
        $this->assertEquals(Logger::WARNING, $formattedRecord['level']);
        $this->assertEquals(Logger::getLevelName(Logger::WARNING), $formattedRecord['level_name']);
        $this->assertEquals('test', $formattedRecord['channel']);
        $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $formattedRecord['datetime']);
        $this->assertEquals('1453410690123', $formattedRecord['datetime']->__toString());
        $this->assertEquals([], $formattedRecord['extra']);
    }

    public function testRecursiveFormat()
    {
        $someObject = new \stdClass();
        $someObject->foo = 'something';
        $someObject->bar = 'stuff';

        $record = [
            'message' => 'some log message',
            'context' => [
                'stuff' => new \DateTimeImmutable('1969-01-21T21:11:30.213000+00:00'),
                'some_object' => $someObject,
                'context_string' => 'some string',
                'context_int' => 123456,
                'except' => new \Exception('exception message', 987),
            ],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable('2016-01-21T21:11:30.213000+00:00'),
            'extra' => [],
        ];

        $formatter = new MongoDBFormatter();
        $formattedRecord = $formatter->format($record);

        $this->assertCount(5, $formattedRecord['context']);
        $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $formattedRecord['context']['stuff']);
        $this->assertEquals('-29731710213', $formattedRecord['context']['stuff']->__toString());
        $this->assertEquals(
            [
                'foo' => 'something',
                'bar' => 'stuff',
                'class' => 'stdClass',
            ],
            $formattedRecord['context']['some_object']
        );
        $this->assertEquals('some string', $formattedRecord['context']['context_string']);
        $this->assertEquals(123456, $formattedRecord['context']['context_int']);

        $this->assertCount(5, $formattedRecord['context']['except']);
        $this->assertEquals('exception message', $formattedRecord['context']['except']['message']);
        $this->assertEquals(987, $formattedRecord['context']['except']['code']);
        $this->assertIsString($formattedRecord['context']['except']['file']);
        $this->assertIsInt($formattedRecord['context']['except']['code']);
        $this->assertIsString($formattedRecord['context']['except']['trace']);
        $this->assertEquals('Exception', $formattedRecord['context']['except']['class']);
    }

    public function testFormatDepthArray()
    {
        $record = [
            'message' => 'some log message',
            'context' => [
                'nest2' => [
                    'property' => 'anything',
                    'nest3' => [
                        'nest4' => 'value',
                        'property' => 'nothing',
                    ],
                ],
            ],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable('2016-01-21T21:11:30.123456+00:00'),
            'extra' => [],
        ];

        $formatter = new MongoDBFormatter(2);
        $formattedResult = $formatter->format($record);

        $this->assertEquals(
            [
                'nest2' => [
                    'property' => 'anything',
                    'nest3' => '[...]',
                ],
            ],
            $formattedResult['context']
        );
    }

    public function testFormatDepthArrayInfiniteNesting()
    {
        $record = [
            'message' => 'some log message',
            'context' => [
                'nest2' => [
                    'property' => 'something',
                    'nest3' => [
                        'property' => 'anything',
                        'nest4' => [
                            'property' => 'nothing',
                        ],
                    ],
                ],
            ],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable('2016-01-21T21:11:30.123456+00:00'),
            'extra' => [],
        ];

        $formatter = new MongoDBFormatter(0);
        $formattedResult = $formatter->format($record);

        $this->assertEquals(
            [
                'nest2' => [
                    'property' => 'something',
                    'nest3' => [
                        'property' => 'anything',
                        'nest4' => [
                            'property' => 'nothing',
                        ],
                    ],
                ],
            ],
            $formattedResult['context']
        );
    }

    public function testFormatDepthObjects()
    {
        $someObject = new \stdClass();
        $someObject->property = 'anything';
        $someObject->nest3 = new \stdClass();
        $someObject->nest3->property = 'nothing';
        $someObject->nest3->nest4 = 'invisible';

        $record = [
            'message' => 'some log message',
            'context' => [
                'nest2' => $someObject,
            ],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable('2016-01-21T21:11:30.123456+00:00'),
            'extra' => [],
        ];

        $formatter = new MongoDBFormatter(2, true);
        $formattedResult = $formatter->format($record);

        $this->assertEquals(
            [
                'nest2' => [
                    'property' => 'anything',
                    'nest3' => '[...]',
                    'class' => 'stdClass',
                ],
            ],
            $formattedResult['context']
        );
    }

    public function testFormatDepthException()
    {
        $record = [
            'message' => 'some log message',
            'context' => [
                'nest2' => new \Exception('exception message', 987),
            ],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable('2016-01-21T21:11:30.123456+00:00'),
            'extra' => [],
        ];

        $formatter = new MongoDBFormatter(2, false);
        $formattedRecord = $formatter->format($record);

        $this->assertEquals('exception message', $formattedRecord['context']['nest2']['message']);
        $this->assertEquals(987, $formattedRecord['context']['nest2']['code']);
        $this->assertEquals('[...]', $formattedRecord['context']['nest2']['trace']);
    }
}
