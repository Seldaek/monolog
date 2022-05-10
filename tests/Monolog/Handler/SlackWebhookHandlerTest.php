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
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\Slack\SlackRecord;

/**
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 * @coversDefaultClass Monolog\Handler\SlackWebhookHandler
 */
class SlackWebhookHandlerTest extends TestCase
{
    const WEBHOOK_URL = 'https://hooks.slack.com/services/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E';

    /**
     * @covers ::__construct
     * @covers ::getSlackRecord
     */
    public function testConstructorMinimal()
    {
        $handler = new SlackWebhookHandler(self::WEBHOOK_URL);
        $record = $this->getRecord();
        $slackRecord = $handler->getSlackRecord();
        $this->assertInstanceOf('Monolog\Handler\Slack\SlackRecord', $slackRecord);
        $this->assertEquals([
            'attachments' => [
                [
                    'fallback' => 'test',
                    'text' => 'test',
                    'color' => SlackRecord::COLOR_WARNING,
                    'fields' => [
                        [
                            'title' => 'Level',
                            'value' => 'WARNING',
                            'short' => false,
                        ],
                    ],
                    'title' => 'Message',
                    'mrkdwn_in' => ['fields'],
                    'ts' => $record->datetime->getTimestamp(),
                    'footer' => null,
                    'footer_icon' => null,
                ],
            ],
        ], $slackRecord->getSlackData($record));
    }

    /**
     * @covers ::__construct
     * @covers ::getSlackRecord
     */
    public function testConstructorFull()
    {
        $handler = new SlackWebhookHandler(
            self::WEBHOOK_URL,
            'test-channel',
            'test-username',
            false,
            ':ghost:',
            false,
            false,
            Level::Debug,
            false
        );

        $slackRecord = $handler->getSlackRecord();
        $this->assertInstanceOf('Monolog\Handler\Slack\SlackRecord', $slackRecord);
        $this->assertEquals([
            'username' => 'test-username',
            'text' => 'test',
            'channel' => 'test-channel',
            'icon_emoji' => ':ghost:',
        ], $slackRecord->getSlackData($this->getRecord()));
    }

    /**
     * @covers ::__construct
     * @covers ::getSlackRecord
     */
    public function testConstructorFullWithAttachment()
    {
        $handler = new SlackWebhookHandler(
            self::WEBHOOK_URL,
            'test-channel-with-attachment',
            'test-username-with-attachment',
            true,
            'https://www.example.com/example.png',
            false,
            false,
            Level::Debug,
            false
        );

        $record = $this->getRecord();
        $slackRecord = $handler->getSlackRecord();
        $this->assertInstanceOf('Monolog\Handler\Slack\SlackRecord', $slackRecord);
        $this->assertEquals([
            'username' => 'test-username-with-attachment',
            'channel' => 'test-channel-with-attachment',
            'attachments' => [
                [
                    'fallback' => 'test',
                    'text' => 'test',
                    'color' => SlackRecord::COLOR_WARNING,
                    'fields' => [
                        [
                            'title' => 'Level',
                            'value' => Level::Warning->getName(),
                            'short' => false,
                        ],
                    ],
                    'mrkdwn_in' => ['fields'],
                    'ts' => $record['datetime']->getTimestamp(),
                    'footer' => 'test-username-with-attachment',
                    'footer_icon' => 'https://www.example.com/example.png',
                    'title' => 'Message',
                ],
            ],
            'icon_url' => 'https://www.example.com/example.png',
        ], $slackRecord->getSlackData($record));
    }

    /**
     * @covers ::getFormatter
     */
    public function testGetFormatter()
    {
        $handler = new SlackWebhookHandler(self::WEBHOOK_URL);
        $formatter = $handler->getFormatter();
        $this->assertInstanceOf('Monolog\Formatter\FormatterInterface', $formatter);
    }

    /**
     * @covers ::setFormatter
     */
    public function testSetFormatter()
    {
        $handler = new SlackWebhookHandler(self::WEBHOOK_URL);
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);
        $this->assertSame($formatter, $handler->getFormatter());
    }
}
