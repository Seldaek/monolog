<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Slack;

use Monolog\Logger;
use Monolog\TestCase;

/**
 * @coversDefaultClass Monolog\Handler\Slack\SlackRecord
 */
class SlackRecordTest extends TestCase
{
    private $channel;

    protected function setUp()
    {
        $this->channel = 'monolog_alerts';
    }

    public function dataGetAttachmentColor()
    {
        return array(
            array(Logger::DEBUG, SlackRecord::COLOR_DEFAULT),
            array(Logger::INFO, SlackRecord::COLOR_GOOD),
            array(Logger::NOTICE, SlackRecord::COLOR_GOOD),
            array(Logger::WARNING, SlackRecord::COLOR_WARNING),
            array(Logger::ERROR, SlackRecord::COLOR_DANGER),
            array(Logger::CRITICAL, SlackRecord::COLOR_DANGER),
            array(Logger::ALERT, SlackRecord::COLOR_DANGER),
            array(Logger::EMERGENCY, SlackRecord::COLOR_DANGER),
        );
    }
    /**
     * @dataProvider dataGetAttachmentColor
     * @param  int $logLevel
     * @param  string $expectedColour RGB hex color or name of Slack color
     * @covers ::getAttachmentColor
     */
    public function testGetAttachmentColor($logLevel, $expectedColour)
    {
        $slackRecord = new SlackRecord('#test');
        $this->assertSame(
            $expectedColour,
            $slackRecord->getAttachmentColor($logLevel)
        );
    }

    public function testAddsChannel()
    {
        $record = new SlackRecord($this->channel);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('channel', $data);
        $this->assertSame($this->channel, $data['channel']);
    }

    public function testStringifyReturnsNullWithNoLineFormatter()
    {
        $slackRecord = new SlackRecord('#test');
        $this->assertNull($slackRecord->stringify(array('foo' => 'bar')));
    }

    public function testAddsDefaultUsername()
    {
        $record = new SlackRecord($this->channel);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('username', $data);
        $this->assertSame('Monolog', $data['username']);
    }

    /**
     * @return array
     */
    public function dataStringify()
    {
        return array(
            array(array(), ''),
            array(array('foo' => 'bar'), 'foo: bar'),
            array(array('Foo' => 'bAr'), 'Foo: bAr'),
        );
    }

    /**
     * @dataProvider dataStringify
     */
    public function testStringifyWithLineFormatter($fields, $expectedResult)
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
        $record = new SlackRecord($this->channel, $username);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('username', $data);
        $this->assertSame($username, $data['username']);
    }

    public function testNoIcon()
    {
        $record = new SlackRecord($this->channel);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayNotHasKey('icon_emoji', $data);
    }

    public function testAddsIcon()
    {
        $record = new SlackRecord($this->channel, 'Monolog', true, 'ghost');
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('icon_emoji', $data);
        $this->assertSame(':ghost:', $data['icon_emoji']);
    }

    public function testAddsEmptyTextIfUseAttachment()
    {
        $record = new SlackRecord($this->channel);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('text', $data);
        $this->assertSame('', $data['text']);
    }

    public function testAttachmentsNotPresentIfNoAttachment()
    {
        $record = new SlackRecord($this->channel, 'Monolog', false);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayNotHasKey('attachments', $data);
    }

    public function testAddsOneAttachment()
    {
        $record = new SlackRecord($this->channel);
        $data = $record->getSlackData($this->getRecord());

        $this->assertArrayHasKey('attachments', $data);
        $this->assertArrayHasKey(0, $data['attachments']);
        $this->assertInternalType('array', $data['attachments'][0]);
    }

    public function testTextEqualsMessageIfNoFormatter()
    {
        $message = 'Test message';
        $record = new SlackRecord($this->channel, 'Monolog', false);
        $data = $record->getSlackData($this->getRecord(Logger::WARNING, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message, $data['text']);
    }

    public function testTextEqualsFormatterOutput()
    {
        $formatter = $this->getMock('Monolog\\Formatter\\FormatterInterface');
        $formatter
            ->expects($this->any())
            ->method('format')
            ->will($this->returnCallback(function ($record) { return $record['message'] . 'test'; }));

        $formatter2 = $this->getMock('Monolog\\Formatter\\FormatterInterface');
        $formatter2
            ->expects($this->any())
            ->method('format')
            ->will($this->returnCallback(function ($record) { return $record['message'] . 'test1'; }));

        $message = 'Test message';
        $record = new SlackRecord($this->channel, 'Monolog', false, null, false, false, $formatter);
        $data = $record->getSlackData($this->getRecord(Logger::WARNING, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message . 'test', $data['text']);

        $record->setFormatter($formatter2);
        $data = $record->getSlackData($this->getRecord(Logger::WARNING, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message . 'test1', $data['text']);
    }

    public function testAddsFallbackAndTextToAttachment()
    {
        $message = 'Test message';
        $record = new SlackRecord($this->channel);
        $data = $record->getSlackData($this->getRecord(Logger::WARNING, $message));

        $this->assertSame($message, $data['attachments'][0]['text']);
        $this->assertSame($message, $data['attachments'][0]['fallback']);
    }

    public function testMapsLevelToColorAttachmentColor()
    {
        $record = new SlackRecord($this->channel);
        $errorLoggerRecord = $this->getRecord(Logger::ERROR);
        $emergencyLoggerRecord = $this->getRecord(Logger::EMERGENCY);
        $warningLoggerRecord = $this->getRecord(Logger::WARNING);
        $infoLoggerRecord = $this->getRecord(Logger::INFO);
        $debugLoggerRecord = $this->getRecord(Logger::DEBUG);

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
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $record = new SlackRecord($this->channel, 'Monolog', true, null, true);
        $data = $record->getSlackData($this->getRecord($level, 'test', array('test' => 1)));

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertSame($levelName, $attachment['title']);
        $this->assertSame(array(), $attachment['fields']);
    }

    public function testAddsShortAttachmentWithContextAndExtra()
    {
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $record = new SlackRecord($this->channel, 'Monolog', true, null, true, true);
        $loggerRecord = $this->getRecord($level, 'test', array('test' => 1));
        $loggerRecord['extra'] = array('tags' => array('web'));
        $data = $record->getSlackData($loggerRecord);

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(2, $attachment['fields']);
        $this->assertSame($levelName, $attachment['title']);
        $this->assertSame(
            array(
                array(
                    'title' => 'Extra',
                    'value' => 'tags: ["web"]',
                    'short' => true
                ),
                array(
                    'title' => 'Context',
                    'value' => 'test: 1',
                    'short' => true
                )
            ),
            $attachment['fields']
        );
    }

    public function testAddsLongAttachmentWithoutContextAndExtra()
    {
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $record = new SlackRecord($this->channel, 'Monolog', true, null);
        $data = $record->getSlackData($this->getRecord($level, 'test', array('test' => 1)));

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(1, $attachment['fields']);
        $this->assertSame('Message', $attachment['title']);
        $this->assertSame(
            array(array(
                'title' => 'Level',
                'value' => $levelName,
                'short' => true
            )),
            $attachment['fields']
        );
    }

    public function testAddsLongAttachmentWithContextAndExtra()
    {
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $record = new SlackRecord($this->channel, 'Monolog', true, null, false, true);
        $loggerRecord = $this->getRecord($level, 'test', array('test' => 1));
        $loggerRecord['extra'] = array('tags' => array('web'));
        $data = $record->getSlackData($loggerRecord);

        $expectedFields = array(
            array(
                'title' => 'Level',
                'value' => $levelName,
                'short' => true,
            ),
            array(
                'title' => 'tags',
                'value' => '["web"]',
                'short' => false
            ),
            array(
                'title' => 'test',
                'value' => 1,
                'short' => false
            )
        );

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
}
