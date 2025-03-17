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

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SlackRecord::class)]
class SlackRecordTest extends \Monolog\Test\MonologTestCase
{
    public static function dataGetAttachmentColor()
    {
        return [
            [Level::Debug, SlackRecord::COLOR_DEFAULT],
            [Level::Info, SlackRecord::COLOR_GOOD],
            [Level::Notice, SlackRecord::COLOR_GOOD],
            [Level::Warning, SlackRecord::COLOR_WARNING],
            [Level::Error, SlackRecord::COLOR_DANGER],
            [Level::Critical, SlackRecord::COLOR_DANGER],
            [Level::Alert, SlackRecord::COLOR_DANGER],
            [Level::Emergency, SlackRecord::COLOR_DANGER],
        ];
    }

    #[DataProvider('dataGetAttachmentColor')]
    public function testGetAttachmentColor(Level $logLevel, string $expectedColour)
    {
        $slackRecord = new SlackRecord();
        $this->assertSame(
            $expectedColour,
            $slackRecord->getAttachmentColor($logLevel)
        );
    }

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

    public static function dataStringify(): array
    {
        $multipleDimensions = [[1, 2]];
        $numericKeys = ['library' => 'monolog'];
        $singleDimension = [1, 'Hello', 'Jordi'];

        return [
            [[], '[]'],
            [$multipleDimensions, json_encode($multipleDimensions, JSON_PRETTY_PRINT)],
            [$numericKeys, json_encode($numericKeys, JSON_PRETTY_PRINT)],
            [$singleDimension, json_encode($singleDimension)],
        ];
    }

    #[DataProvider('dataStringify')]
    public function testStringify($fields, $expectedResult)
    {
        $slackRecord = new SlackRecord(
            '#test',
            'test',
            true,
            null,
            true,
            true
        );

        $this->assertSame($expectedResult, $slackRecord->stringify($fields));
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
        $slackRecord = new SlackRecord(null, null, false, 'ghost');
        $data = $slackRecord->getSlackData($record);

        $slackRecord2 = new SlackRecord(null, null, false, 'http://github.com/Seldaek/monolog');
        $data2 = $slackRecord2->getSlackData($record);

        $this->assertArrayHasKey('icon_emoji', $data);
        $this->assertSame(':ghost:', $data['icon_emoji']);
        $this->assertArrayHasKey('icon_url', $data2);
        $this->assertSame('http://github.com/Seldaek/monolog', $data2['icon_url']);
    }

    public function testAttachmentsNotPresentIfNoAttachment()
    {
        $record = new SlackRecord(null, null, false);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayNotHasKey('attachments', $data);
    }

    public function testAddsOneAttachment()
    {
        $record = new SlackRecord();
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('attachments', $data);
        $this->assertArrayHasKey(0, $data['attachments']);
        $this->assertIsArray($data['attachments'][0]);
    }

    public function testTextEqualsMessageIfNoAttachment()
    {
        $message = 'Test message';
        $record = new SlackRecord(null, null, false);
        $data = $record->getSlackData($this->getRecord(Level::Warning, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message, $data['text']);
    }

    public function testTextEqualsFormatterOutput()
    {
        $formatter = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $formatter
            ->expects($this->any())
            ->method('format')
            ->willReturnCallback(function ($record) {
                return $record->message . 'test';
            });

        $formatter2 = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $formatter2
            ->expects($this->any())
            ->method('format')
            ->willReturnCallback(function ($record) {
                return $record->message . 'test1';
            });

        $message = 'Test message';
        $record = new SlackRecord(null, null, false, null, false, false, [], $formatter);
        $data = $record->getSlackData($this->getRecord(Level::Warning, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message . 'test', $data['text']);

        $record->setFormatter($formatter2);
        $data = $record->getSlackData($this->getRecord(Level::Warning, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message . 'test1', $data['text']);
    }

    public function testAddsFallbackAndTextToAttachment()
    {
        $message = 'Test message';
        $record = new SlackRecord(null);
        $data = $record->getSlackData($this->getRecord(Level::Warning, $message));

        $this->assertSame($message, $data['attachments'][0]['text']);
        $this->assertSame($message, $data['attachments'][0]['fallback']);
    }

    public function testMapsLevelToColorAttachmentColor()
    {
        $record = new SlackRecord(null);
        $errorLoggerRecord = $this->getRecord(Level::Error);
        $emergencyLoggerRecord = $this->getRecord(Level::Emergency);
        $warningLoggerRecord = $this->getRecord(Level::Warning);
        $infoLoggerRecord = $this->getRecord(Level::Info);
        $debugLoggerRecord = $this->getRecord(Level::Debug);

        $data = $record->getSlackData($errorLoggerRecord);
        $this->assertSame(SlackRecord::COLOR_DANGER, $data['attachments'][0]['color']);

        $data = $record->getSlackData($emergencyLoggerRecord);
        $this->assertSame(SlackRecord::COLOR_DANGER, $data['attachments'][0]['color']);

        $data = $record->getSlackData($warningLoggerRecord);
        $this->assertSame(SlackRecord::COLOR_WARNING, $data['attachments'][0]['color']);

        $data = $record->getSlackData($infoLoggerRecord);
        $this->assertSame(SlackRecord::COLOR_GOOD, $data['attachments'][0]['color']);

        $data = $record->getSlackData($debugLoggerRecord);
        $this->assertSame(SlackRecord::COLOR_DEFAULT, $data['attachments'][0]['color']);
    }

    public function testAddsShortAttachmentWithoutContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $record = new SlackRecord(null, null, true, null, true);
        $data = $record->getSlackData($this->getRecord($level, 'test', ['test' => 1]));

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertSame($levelName, $attachment['title']);
        $this->assertSame([], $attachment['fields']);
    }

    public function testAddsShortAttachmentWithContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $context = ['test' => 1];
        $extra = ['tags' => ['web']];
        $record = new SlackRecord(null, null, true, null, true, true);
        $loggerRecord = $this->getRecord($level, 'test', $context);
        $loggerRecord['extra'] = $extra;
        $data = $record->getSlackData($loggerRecord);

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(2, $attachment['fields']);
        $this->assertSame($levelName, $attachment['title']);
        $this->assertSame(
            [
                [
                    'title' => 'Extra',
                    'value' => \sprintf('```%s```', json_encode($extra, JSON_PRETTY_PRINT)),
                    'short' => false,
                ],
                [
                    'title' => 'Context',
                    'value' => \sprintf('```%s```', json_encode($context, JSON_PRETTY_PRINT)),
                    'short' => false,
                ],
            ],
            $attachment['fields']
        );
    }

    public function testAddsLongAttachmentWithoutContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $record = new SlackRecord(null, null, true, null);
        $data = $record->getSlackData($this->getRecord($level, 'test', ['test' => 1]));

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(1, $attachment['fields']);
        $this->assertSame('Message', $attachment['title']);
        $this->assertSame(
            [[
                'title' => 'Level',
                'value' => $levelName,
                'short' => false,
            ]],
            $attachment['fields']
        );
    }

    public function testAddsLongAttachmentWithContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $context = ['test' => 1];
        $extra = ['tags' => ['web']];
        $record = new SlackRecord(null, null, true, null, false, true);
        $loggerRecord = $this->getRecord($level, 'test', $context);
        $loggerRecord['extra'] = $extra;
        $data = $record->getSlackData($loggerRecord);

        $expectedFields = [
            [
                'title' => 'Level',
                'value' => $levelName,
                'short' => false,
            ],
            [
                'title' => 'Tags',
                'value' => \sprintf('```%s```', json_encode($extra['tags'])),
                'short' => false,
            ],
            [
                'title' => 'Test',
                'value' => $context['test'],
                'short' => false,
            ],
        ];

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(3, $attachment['fields']);
        $this->assertSame('Message', $attachment['title']);
        $this->assertSame(
            $expectedFields,
            $attachment['fields']
        );
    }

    public function testAddsTimestampToAttachment()
    {
        $record = $this->getRecord();
        $slackRecord = new SlackRecord();
        $data = $slackRecord->getSlackData($this->getRecord());

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('ts', $attachment);
        $this->assertSame($record->datetime->getTimestamp(), $attachment['ts']);
    }

    public function testContextHasException()
    {
        $record = $this->getRecord(Level::Critical, 'This is a critical message.', ['exception' => new \Exception()]);
        $slackRecord = new SlackRecord(null, null, true, null, false, true);
        $data = $slackRecord->getSlackData($record);
        $this->assertIsString($data['attachments'][0]['fields'][1]['value']);
    }

    public function testExcludeExtraAndContextFields()
    {
        $record = $this->getRecord(
            Level::Warning,
            'test',
            context: ['info' => ['library' => 'monolog', 'author' => 'Jordi']],
            extra: ['tags' => ['web', 'cli']],
        );

        $slackRecord = new SlackRecord(null, null, true, null, false, true, ['context.info.library', 'extra.tags.1']);
        $data = $slackRecord->getSlackData($record);
        $attachment = $data['attachments'][0];

        $expected = [
            [
                'title' => 'Info',
                'value' => \sprintf('```%s```', json_encode(['author' => 'Jordi'], JSON_PRETTY_PRINT)),
                'short' => false,
            ],
            [
                'title' => 'Tags',
                'value' => \sprintf('```%s```', json_encode(['web'])),
                'short' => false,
            ],
        ];

        foreach ($expected as $field) {
            $this->assertNotFalse(array_search($field, $attachment['fields']));
            break;
        }
    }
}
