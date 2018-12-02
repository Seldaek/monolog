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
use Monolog\Formatter\LineFormatter;
use Raven_Client;

class RavenHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('Raven_Client')) {
            $this->markTestSkipped('sentry/sentry not installed');
        }

        if (version_compare(Raven_Client::VERSION, '0.16.0', '>=')) {
            require_once __DIR__ . '/MockRavenClient-gte-0-16-0.php';
        } else {
            require_once __DIR__ . '/MockRavenClient.php';
        }
    }

    /**
     * @covers Monolog\Handler\RavenHandler::__construct
     */
    public function testConstruct()
    {
        $handler = new RavenHandler($this->getRavenClient());
        $this->assertInstanceOf('Monolog\Handler\RavenHandler', $handler);
    }

    protected function getHandler($ravenClient)
    {
        return new RavenHandler($ravenClient);
    }

    protected function getRavenClient()
    {
        $dsn = 'http://43f6017361224d098402974103bfc53d:a6a0538fc2934ba2bed32e08741b2cd3@marca.python.live.cheggnet.com:9000/1';

        return new MockRavenClient($dsn);
    }

    public function testDebug()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $record = $this->getRecord(Logger::DEBUG, 'A test debug message');
        $handler->handle($record);

        $this->assertEquals($ravenClient::DEBUG, $ravenClient->lastData['level']);
        $this->assertContains($record['message'], $ravenClient->lastData['message']);
    }

    public function testWarning()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $record = $this->getRecord(Logger::WARNING, 'A test warning message');
        $handler->handle($record);

        $this->assertEquals($ravenClient::WARNING, $ravenClient->lastData['level']);
        $this->assertContains($record['message'], $ravenClient->lastData['message']);
    }

    public function testTag()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $tags = [1, 2, 'foo'];
        $record = $this->getRecord(Logger::INFO, 'test', ['tags' => $tags]);
        $handler->handle($record);

        $this->assertEquals($tags, $ravenClient->lastData['tags']);
    }

    public function testExtraParameters()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $checksum = '098f6bcd4621d373cade4e832627b4f6';
        $release = '05a671c66aefea124cc08b76ea6d30bb';
        $eventId = '31423';
        $record = $this->getRecord(Logger::INFO, 'test', ['checksum' => $checksum, 'release' => $release, 'event_id' => $eventId]);
        $handler->handle($record);

        $this->assertEquals($checksum, $ravenClient->lastData['checksum']);
        $this->assertEquals($release, $ravenClient->lastData['release']);
        $this->assertEquals($eventId, $ravenClient->lastData['event_id']);
    }

    public function testFingerprint()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $fingerprint = ['{{ default }}', 'other value'];
        $record = $this->getRecord(Logger::INFO, 'test', ['fingerprint' => $fingerprint]);
        $handler->handle($record);

        $this->assertEquals($fingerprint, $ravenClient->lastData['fingerprint']);
    }

    public function testUserContext()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $recordWithNoContext = $this->getRecord(Logger::INFO, 'test with default user context');
        // set user context 'externally'

        $user = [
            'id' => '123',
            'email' => 'test@test.com',
        ];

        $recordWithContext = $this->getRecord(Logger::INFO, 'test', ['user' => $user]);

        $ravenClient->user_context(['id' => 'test_user_id']);
        // handle context
        $handler->handle($recordWithContext);
        $this->assertEquals($user, $ravenClient->lastData['user']);

        // check to see if its reset
        $handler->handle($recordWithNoContext);
        $this->assertInternalType('array', $ravenClient->context->user);
        $this->assertSame('test_user_id', $ravenClient->context->user['id']);

        // handle with null context
        $ravenClient->user_context(null, false);
        $handler->handle($recordWithContext);
        $this->assertEquals($user, $ravenClient->lastData['user']);

        // check to see if its reset
        $handler->handle($recordWithNoContext);
        $this->assertNull($ravenClient->context->user);
    }

    public function testException()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        try {
            $this->methodThatThrowsAnException();
        } catch (\Exception $e) {
            $record = $this->getRecord(Logger::ERROR, $e->getMessage(), ['exception' => $e]);
            $handler->handle($record);
        }

        $this->assertEquals('[test] ' . $record['message'], $ravenClient->lastData['message']);
    }

    public function testHandleBatch()
    {
        $records = $this->getMultipleRecords();
        $records[] = $this->getRecord(Logger::WARNING, 'warning');
        $records[] = $this->getRecord(Logger::WARNING, 'warning');

        $logFormatter = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $logFormatter->expects($this->once())->method('formatBatch');

        $formatter = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $formatter->expects($this->once())->method('format')->with($this->callback(function ($record) {
            return $record['level'] == 400;
        }));

        $handler = $this->getHandler($this->getRavenClient());
        $handler->setBatchFormatter($logFormatter);
        $handler->setFormatter($formatter);
        $handler->handleBatch($records);
    }

    public function testHandleBatchDoNothingIfRecordsAreBelowLevel()
    {
        $records = [
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information'),
        ];

        $handler = $this->getMockBuilder('Monolog\Handler\RavenHandler')
            ->setMethods(['handle'])
            ->setConstructorArgs([$this->getRavenClient()])
            ->getMock();
        $handler->expects($this->never())->method('handle');
        $handler->setLevel(Logger::ERROR);
        $handler->handleBatch($records);
    }

    public function testHandleBatchPicksProperMessage()
    {
        $records = array(
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information 1'),
            $this->getRecord(Logger::ERROR, 'error 1'),
            $this->getRecord(Logger::WARNING, 'warning'),
            $this->getRecord(Logger::ERROR, 'error 2'),
            $this->getRecord(Logger::INFO, 'information 2'),
        );

        $logFormatter = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $logFormatter->expects($this->once())->method('formatBatch');

        $formatter = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $formatter->expects($this->once())->method('format')->with($this->callback(function ($record) use ($records) {
            return $record['message'] == 'error 1';
        }));

        $handler = $this->getHandler($this->getRavenClient());
        $handler->setBatchFormatter($logFormatter);
        $handler->setFormatter($formatter);
        $handler->handleBatch($records);
    }

    public function testGetSetBatchFormatter()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $handler->setBatchFormatter($formatter = new LineFormatter());
        $this->assertSame($formatter, $handler->getBatchFormatter());
    }

    public function testRelease()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);
        $release = 'v42.42.42';
        $handler->setRelease($release);
        $record = $this->getRecord(Logger::INFO, 'test');
        $handler->handle($record);
        $this->assertEquals($release, $ravenClient->lastData['release']);

        $localRelease = 'v41.41.41';
        $record = $this->getRecord(Logger::INFO, 'test', ['release' => $localRelease]);
        $handler->handle($record);
        $this->assertEquals($localRelease, $ravenClient->lastData['release']);
    }

    public function testEnvironment()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);
        $handler->setEnvironment('preprod');

        $handler->handle($this->getRecord(Logger::INFO, 'Hello 👋 from PREPROD env'));
        $this->assertEquals('preprod', $ravenClient->lastData['environment']);

        $handler->handle($this->getRecord(Logger::INFO, 'Hello 👋 from STAGING env', ['environment' => 'staging']));
        $this->assertEquals('staging', $ravenClient->lastData['environment']);
    }

    public function testBreadcrumbs()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $handler->addBreadcrumb($crumb1 = [
            'category' => 'test',
            'level' => 'info',
            'message' => 'Step 1: user auth',
        ]);

        $handler->addBreadcrumb($crumb2 = [
            'category' => 'test',
            'level' => 'info',
            'message' => 'Step 2: prepare user redirect',
        ]);

        $handler->handle($this->getRecord(Logger::ERROR, 'ERROR 💥'));
        $breadcrumbs = $ravenClient->breadcrumbs->fetch();
        $this->assertCount(2, $breadcrumbs);
        $this->assertSame('test', $breadcrumbs[0]['category']);
        $this->assertSame('info', $breadcrumbs[0]['level']);
        $this->assertSame('Step 1: user auth', $breadcrumbs[0]['message']);

        $this->assertSame('test', $breadcrumbs[1]['category']);
        $this->assertSame('info', $breadcrumbs[1]['level']);
        $this->assertSame('Step 2: prepare user redirect', $breadcrumbs[1]['message']);
        
        $handler->resetBreadcrumbs();
        $handler->handle($this->getRecord(Logger::INFO, 'Hello!'));
        $this->assertEmpty($ravenClient->breadcrumbs->fetch());
    }

    private function methodThatThrowsAnException()
    {
        throw new \Exception('This is an exception');
    }
}
