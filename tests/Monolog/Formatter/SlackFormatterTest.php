<?php


namespace Monolog\Formatter;

use Monolog\Logger;
use Monolog\Test\TestCase;

/**
 * @covers \Monolog\Formatter\SlackFormatter
 */
class SlackFormatterTest extends TestCase
{
    public function dataGetAttachmentColor()
    {
        return array(
            array(Logger::DEBUG, SlackFormatter::COLOR_DEFAULT),
            array(Logger::INFO, SlackFormatter::COLOR_GOOD),
            array(Logger::NOTICE, SlackFormatter::COLOR_GOOD),
            array(Logger::WARNING, SlackFormatter::COLOR_WARNING),
            array(Logger::ERROR, SlackFormatter::COLOR_DANGER),
            array(Logger::CRITICAL, SlackFormatter::COLOR_DANGER),
            array(Logger::ALERT, SlackFormatter::COLOR_DANGER),
            array(Logger::EMERGENCY, SlackFormatter::COLOR_DANGER),
        );
    }

    /**
     * @dataProvider dataGetAttachmentColor
     */
    public function testGetAttachmentColor($logLevel, $expectedColour)
    {
        $slackFormatter = new SlackFormatter();
        $this->assertSame(
            $expectedColour,
            $slackFormatter->getAttachmentColor($logLevel)
        );
    }

    /**
     * @return array
     */
    public function dataStringify()
    {
        $multipleDimensions = array(array(1, 2));
        $numericKeys = array('library' => 'monolog');
        $singleDimension = array(1, 'Hello', 'Jordi');

        return array(
            array(array(), '[]'),
            array($multipleDimensions, json_encode($multipleDimensions, JSON_PRETTY_PRINT)),
            array($numericKeys, json_encode($numericKeys, JSON_PRETTY_PRINT)),
            array($singleDimension, json_encode($singleDimension)),
        );
    }

    /**
     * @dataProvider dataStringify
     */
    public function testStringify($fields, $expectedResult)
    {
        $slackRecord = new SlackFormatter(true, true, true);

        $this->assertSame($expectedResult, $slackRecord->stringify($fields));
    }

    public function testAttachmentsNotPresentIfNoAttachment()
    {
        $record = new SlackFormatter(false);
        $data = $record->format($this->getRecord());

        $this->assertArrayNotHasKey('attachments', $data);
    }

    public function testAddsOneAttachment()
    {
        $record = new SlackFormatter();
        $data = $record->format($this->getRecord());

        $this->assertArrayHasKey('attachments', $data);
        $this->assertArrayHasKey(0, $data['attachments']);
        $this->assertIsArray($data['attachments'][0]);
    }

    public function testTextEqualsMessageIfNoAttachment()
    {
        $message = 'Test message';
        $record = new SlackFormatter(false);
        $data = $record->format($this->getRecord(Logger::WARNING, $message));

        $this->assertArrayHasKey('text', $data);
        $this->assertSame($message, $data['text']);
    }

    public function testAddsFallbackAndTextToAttachment()
    {
        $message = 'Test message';
        $record = new SlackFormatter();
        $data = $record->format($this->getRecord(Logger::WARNING, $message));

        $this->assertSame($message, $data['attachments'][0]['text']);
        $this->assertSame($message, $data['attachments'][0]['fallback']);
    }

    public function testMapsLevelToColorAttachmentColor()
    {
        $record = new SlackFormatter();
        $errorLoggerRecord = $this->getRecord(Logger::ERROR);
        $emergencyLoggerRecord = $this->getRecord(Logger::EMERGENCY);
        $warningLoggerRecord = $this->getRecord(Logger::WARNING);
        $infoLoggerRecord = $this->getRecord(Logger::INFO);
        $debugLoggerRecord = $this->getRecord(Logger::DEBUG);

        $data = $record->format($errorLoggerRecord);
        $this->assertSame(SlackFormatter::COLOR_DANGER, $data['attachments'][0]['color']);

        $data = $record->format($emergencyLoggerRecord);
        $this->assertSame(SlackFormatter::COLOR_DANGER, $data['attachments'][0]['color']);

        $data = $record->format($warningLoggerRecord);
        $this->assertSame(SlackFormatter::COLOR_WARNING, $data['attachments'][0]['color']);

        $data = $record->format($infoLoggerRecord);
        $this->assertSame(SlackFormatter::COLOR_GOOD, $data['attachments'][0]['color']);

        $data = $record->format($debugLoggerRecord);
        $this->assertSame(SlackFormatter::COLOR_DEFAULT, $data['attachments'][0]['color']);
    }

    public function testAddsShortAttachmentWithoutContextAndExtra()
    {
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $record = new SlackFormatter(true, true);
        $data = $record->format($this->getRecord($level, 'test', array('test' => 1)));

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
        $context = array('test' => 1);
        $extra = array('tags' => array('web'));
        $record = new SlackFormatter(true, true, true);
        $loggerRecord = $this->getRecord($level, 'test', $context);
        $loggerRecord['extra'] = $extra;
        $data = $record->format($loggerRecord);

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(2, $attachment['fields']);
        $this->assertSame($levelName, $attachment['title']);
        $this->assertSame(
            array(
                array(
                    'title' => 'Extra',
                    'value' => sprintf('```%s```', json_encode($extra, JSON_PRETTY_PRINT)),
                    'short' => false,
                ),
                array(
                    'title' => 'Context',
                    'value' => sprintf('```%s```', json_encode($context, JSON_PRETTY_PRINT)),
                    'short' => false,
                ),
            ),
            $attachment['fields']
        );
    }

    public function testAddsLongAttachmentWithoutContextAndExtra()
    {
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $record = new SlackFormatter(true);
        $data = $record->format($this->getRecord($level, 'test', array('test' => 1)));

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('title', $attachment);
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertCount(1, $attachment['fields']);
        $this->assertSame('Message', $attachment['title']);
        $this->assertSame(
            array(array(
                'title' => 'Level',
                'value' => $levelName,
                'short' => false,
            )),
            $attachment['fields']
        );
    }

    public function testAddsLongAttachmentWithContextAndExtra()
    {
        $level = Logger::ERROR;
        $levelName = Logger::getLevelName($level);
        $context = array('test' => 1);
        $extra = array('tags' => array('web'));
        $record = new SlackFormatter(true, false, true);
        $loggerRecord = $this->getRecord($level, 'test', $context);
        $loggerRecord['extra'] = $extra;
        $data = $record->format($loggerRecord);

        $expectedFields = array(
            array(
                'title' => 'Level',
                'value' => $levelName,
                'short' => false,
            ),
            array(
                'title' => 'Tags',
                'value' => sprintf('```%s```', json_encode($extra['tags'])),
                'short' => false,
            ),
            array(
                'title' => 'Test',
                'value' => $context['test'],
                'short' => false,
            ),
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

    public function testAddsTimestampToAttachment()
    {
        $record = $this->getRecord();
        $slackRecord = new SlackFormatter();
        $data = $slackRecord->format($this->getRecord());

        $attachment = $data['attachments'][0];
        $this->assertArrayHasKey('ts', $attachment);
        $this->assertSame($record['datetime']->getTimestamp(), $attachment['ts']);
    }

    public function testContextHasException()
    {
        $record = $this->getRecord(Logger::CRITICAL, 'This is a critical message.', array('exception' => new \Exception()));
        $slackRecord = new SlackFormatter( true, false, true);
        $data = $slackRecord->format($record);
        $this->assertIsString($data['attachments'][0]['fields'][1]['value']);
    }

    public function testExcludeExtraAndContextFields()
    {
        $record = $this->getRecord(
            Logger::WARNING,
            'test',
            array('info' => array('library' => 'monolog', 'author' => 'Jordi'))
        );
        $record['extra'] = array('tags' => array('web', 'cli'));

        $slackRecord = new SlackFormatter(
            true,
            false,
            true,
            array('context.info.library', 'extra.absent', 'extra.tags.1')
        );
        $data = $slackRecord->format($record);
        $attachment = $data['attachments'][0];

        $expected = array(
            array(
                'title' => 'Info',
                'value' => sprintf('```%s```', json_encode(array('author' => 'Jordi'), JSON_PRETTY_PRINT)),
                'short' => false,
            ),
            array(
                'title' => 'Tags',
                'value' => sprintf('```%s```', json_encode(array('web'))),
                'short' => false,
            ),
        );

        foreach ($expected as $field) {
            $this->assertNotFalse(array_search($field, $attachment['fields']));
            break;
        }
    }
}
