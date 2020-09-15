<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Slack;

use InvalidArgumentException;
use Monolog\Logger;
use Monolog\Test\TestCase;

/**
 * @covers \Monolog\Handler\Slack\SlackRecord
 */
class SlackRecordTest extends TestCase
{
    public function testAddsChannel()
    {
        $channel = '#test';
        $record = new SlackRecord($channel);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('channel', $data);
        $this->assertSame($channel, $data['channel']);
    }

    public function testNoUsernameByDefault()
    {
        $record = new SlackRecord();
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayNotHasKey('username', $data);
    }

    public function testAddsCustomUsername()
    {
        $username = 'Monolog bot';
        $record = new SlackRecord(null, $username);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('username', $data);
        $this->assertSame($username, $data['username']);
    }

    public function testNoIcon()
    {
        $record = new SlackRecord();
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayNotHasKey('icon_emoji', $data);
    }

    public function testAddsIcon()
    {
        $record = $this->getRecord();
        $slackRecord = new SlackRecord(null, null, 'ghost');
        $data = $slackRecord->getSlackData($record);

        $slackRecord2 = new SlackRecord(null, null, 'http://github.com/Seldaek/monolog');
        $data2 = $slackRecord2->getSlackData($record);

        $this->assertArrayHasKey('icon_emoji', $data);
        $this->assertSame(':ghost:', $data['icon_emoji']);
        $this->assertArrayHasKey('icon_url', $data2);
        $this->assertSame('http://github.com/Seldaek/monolog', $data2['icon_url']);
    }

    public function testCanCreateMessageFromFormattedString()
    {
        $record = $this->getRecord(Logger::WARNING, 'Discarded message');
        $record['formatted'] = 'Formatted message';

        $slackRecord = new SlackRecord(null, null, null);
        $data = $slackRecord->getSlackData($record);

        $this->assertArrayHasKey('text', $data);
        $this->assertSame('Formatted message', $data['text']);
    }

    public function testCanCreateMessageFromPartialSchema()
    {
        $record = $this->getRecord(Logger::WARNING, 'a message');
        $record['formatted'] = [
            'text' => 'Some text',
            'attachments' => [
                'text' => 'attachment text',
            ],
        ];

        $slackRecord = new SlackRecord('channel1','Tom');
        $message = $slackRecord->getSlackData($record);

        $this->assertEquals(
            [
                'channel' => 'channel1',
                'username' => 'Tom',
                'text' => 'Some text',
                'attachments' => [
                    'text' => 'attachment text',
                ],
            ],
            $message
        );
    }

    public function testCantCreateMessageFromObject()
    {
        $record = $this->getRecord(Logger::WARNING, 'a message');
        $record['formatted'] = (object) [
            'a' => 1,
            'b' => 2,
        ];

        $slackRecord = new SlackRecord('channel1','Tom');

        try {
            $slackRecord->getSlackData($record);
            $this->fail('Expected an exception');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                'Expected formatter to return a scalar or a slack message array. Instead got type object',
                $e->getMessage()
            );
        }
    }

    public function testFallbackToMessageWithoutFormatter()
    {
        $record = $this->getRecord(Logger::WARNING, 'a message');

        $slackRecord = new SlackRecord('channel1','Tom');
        $message = $slackRecord->getSlackData($record);

        $this->assertEquals(
            [
                'channel' => 'channel1',
                'username' => 'Tom',
                'text' => 'a message',
            ],
            $message
        );
    }
}
