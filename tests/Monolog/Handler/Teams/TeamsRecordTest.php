<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Handler\Teams;

use Monolog\Handler\Teams\TeamsRecord;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TeamsRecord::class)]
class TeamsRecordTest extends \Monolog\Test\MonologTestCase
{
    public static function dataGetContainerStyle()
    {
        return [
            [Level::Debug, TeamsRecord::COLOR_DEFAULT],
            [Level::Info, TeamsRecord::COLOR_GOOD],
            [Level::Notice, TeamsRecord::COLOR_GOOD],
            [Level::Warning, TeamsRecord::COLOR_WARNING],
            [Level::Error, TeamsRecord::COLOR_ATTENTION],
            [Level::Critical, TeamsRecord::COLOR_ATTENTION],
            [Level::Alert, TeamsRecord::COLOR_ATTENTION],
            [Level::Emergency, TeamsRecord::COLOR_ATTENTION],
        ];
    }

    #[DataProvider('dataGetContainerStyle')]
    public function testGetContainerStyle(Level $logLevel, string $expectedColour)
    {
        $teamsRecord = new TeamsRecord();
        $this->assertSame(
            $expectedColour,
            $teamsRecord->getContainerStyle($logLevel)
        );
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
        $teamsRecord = new TeamsRecord(true);

        $this->assertSame($expectedResult, $teamsRecord->stringify($fields));
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
        $record = new TeamsRecord(false, [], [], $formatter);
        $data = $record->getAdaptiveCardPayload($this->getRecord(Level::Warning, $message));

        $this->assertArrayHasKey('text', $data['attachments'][0]['content']['body'][0]['items'][0]);
        $this->assertSame($message . 'test', $data['attachments'][0]['content']['body'][0]['items'][0]['text']);

        $record->setFormatter($formatter2);
        $data = $record->getAdaptiveCardPayload($this->getRecord(Level::Warning, $message));

        $this->assertArrayHasKey('text', $data['attachments'][0]['content']['body'][0]['items'][0]);
        $this->assertSame($message . 'test1', $data['attachments'][0]['content']['body'][0]['items'][0]['text']);
    }

    public function testMapsLevelToContainerStyle()
    {
        $record = new TeamsRecord();
        $errorLoggerRecord = $this->getRecord(Level::Error);
        $emergencyLoggerRecord = $this->getRecord(Level::Emergency);
        $warningLoggerRecord = $this->getRecord(Level::Warning);
        $infoLoggerRecord = $this->getRecord(Level::Info);
        $debugLoggerRecord = $this->getRecord(Level::Debug);

        $data = $record->getAdaptiveCardPayload($errorLoggerRecord);
        $this->assertSame(TeamsRecord::COLOR_ATTENTION, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($emergencyLoggerRecord);
        $this->assertSame(TeamsRecord::COLOR_ATTENTION, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($warningLoggerRecord);
        $this->assertSame(TeamsRecord::COLOR_WARNING, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($infoLoggerRecord);
        $this->assertSame(TeamsRecord::COLOR_GOOD, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($debugLoggerRecord);
        $this->assertSame(TeamsRecord::COLOR_DEFAULT, $data['attachments'][0]['content']['body'][0]['style']);
    }

    public function testWithoutContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $record = new TeamsRecord(false);
        $data = $record->getAdaptiveCardPayload($this->getRecord($level, 'test', ['test' => 1]));

        $factSet = $data['attachments'][0]['content']['body'][1]['items'][0];
        $this->assertArrayHasKey('type', $factSet);
        $this->assertArrayHasKey('facts', $factSet);
        $this->assertCount(1, $factSet['facts']);
        $this->assertSame('FactSet', $factSet['type']);
        $this->assertSame(
            [[
                'title' => 'Level',
                'value' => $levelName,
            ]],
            $factSet['facts']
        );
    }

    public function testWithContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $context = ['test' => 1];
        $extra = ['tags' => ['web']];
        $record = new TeamsRecord(true);
        $loggerRecord = $this->getRecord($level, 'test', $context);
        $loggerRecord['extra'] = $extra;
        $data = $record->getAdaptiveCardPayload($loggerRecord);

        $expectedFields = [
            [
                'title' => 'Level',
                'value' => $levelName,
            ],
            [
                'title' => 'Tags',
                'value' => json_encode($extra['tags']),
            ],
            [
                'title' => 'Test',
                'value' => $context['test'],
            ],
        ];

        $factSet = $data['attachments'][0]['content']['body'][1]['items'][0];
        $this->assertArrayHasKey('type', $factSet);
        $this->assertArrayHasKey('facts', $factSet);
        $this->assertCount(3, $factSet['facts']);
        $this->assertSame('FactSet', $factSet['type']);
        $this->assertSame(
            $expectedFields,
            $factSet['facts']
        );
    }

    public function testContextHasException()
    {
        $record = $this->getRecord(Level::Critical, 'This is a critical message.', ['exception' => new \Exception()]);
        $teamsRecord = new TeamsRecord(true);
        $data = $teamsRecord->getAdaptiveCardPayload($record);
        $this->assertIsString($data['attachments'][0]['content']['body'][1]['items'][0]['facts'][1]['value']);
    }

    public function testExcludeExtraAndContextFields()
    {
        $record = $this->getRecord(
            Level::Warning,
            'test',
            context: ['info' => ['library' => 'monolog', 'author' => 'Jordi']],
            extra: ['tags' => ['web', 'cli']],
        );

        $teamsRecord = new TeamsRecord(true, ['context.info.library', 'extra.tags.1']);
        $data = $teamsRecord->getAdaptiveCardPayload($record);
        $facts = $data['attachments'][0]['content']['body'][1]['items'][0]['facts'];

        $expected = [
            [
                'title' => 'Info',
                'value' => json_encode(['author' => 'Jordi'], JSON_PRETTY_PRINT),
            ],
            [
                'title' => 'Tags',
                'value' => json_encode(['web']),
            ],
        ];

        foreach ($expected as $field) {
            $this->assertNotFalse(array_search($field, $facts));
            break;
        }
    }
}
