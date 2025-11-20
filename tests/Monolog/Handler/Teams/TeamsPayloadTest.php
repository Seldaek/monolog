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

use Monolog\Handler\Teams\TeamsPayload;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TeamsPayload::class)]
class TeamsPayloadTest extends \Monolog\Test\MonologTestCase
{
    public static function dataGetContainerStyle()
    {
        return [
            [Level::Debug, TeamsPayload::COLOR_DEFAULT],
            [Level::Info, TeamsPayload::COLOR_GOOD],
            [Level::Notice, TeamsPayload::COLOR_GOOD],
            [Level::Warning, TeamsPayload::COLOR_WARNING],
            [Level::Error, TeamsPayload::COLOR_ATTENTION],
            [Level::Critical, TeamsPayload::COLOR_ATTENTION],
            [Level::Alert, TeamsPayload::COLOR_ATTENTION],
            [Level::Emergency, TeamsPayload::COLOR_ATTENTION],
        ];
    }

    #[DataProvider('dataGetContainerStyle')]
    public function testGetContainerStyle(Level $logLevel, string $expectedColour)
    {
        $teamsPayload = new TeamsPayload();

        $reflection = new \ReflectionClass(TeamsPayload::class);
        $method = $reflection->getMethod('getContainerStyle');
        $method->setAccessible(true);

        $this->assertSame($expectedColour, $method->invoke($teamsPayload, $logLevel));
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
        $teamsPayload = new TeamsPayload(true);

        $reflection = new \ReflectionClass(TeamsPayload::class);
        $method = $reflection->getMethod('stringify');
        $method->setAccessible(true);

        $this->assertSame($expectedResult, $method->invoke($teamsPayload, $fields));
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
        $record = new TeamsPayload(false, true, [], []);
        $data = $record->getAdaptiveCardPayload($this->getRecord(Level::Warning, $message), $formatter);

        $this->assertArrayHasKey('text', $data['attachments'][0]['content']['body'][0]['items'][0]);
        $this->assertSame($message . 'test', $data['attachments'][0]['content']['body'][0]['items'][0]['text']);

        $data = $record->getAdaptiveCardPayload($this->getRecord(Level::Warning, $message), $formatter2);

        $this->assertArrayHasKey('text', $data['attachments'][0]['content']['body'][0]['items'][0]);
        $this->assertSame($message . 'test1', $data['attachments'][0]['content']['body'][0]['items'][0]['text']);
    }

    public function testMapsLevelToContainerStyle()
    {
        $record = new TeamsPayload();
        $errorLoggerRecord = $this->getRecord(Level::Error);
        $emergencyLoggerRecord = $this->getRecord(Level::Emergency);
        $warningLoggerRecord = $this->getRecord(Level::Warning);
        $infoLoggerRecord = $this->getRecord(Level::Info);
        $debugLoggerRecord = $this->getRecord(Level::Debug);

        $data = $record->getAdaptiveCardPayload($errorLoggerRecord);
        $this->assertSame(TeamsPayload::COLOR_ATTENTION, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($emergencyLoggerRecord);
        $this->assertSame(TeamsPayload::COLOR_ATTENTION, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($warningLoggerRecord);
        $this->assertSame(TeamsPayload::COLOR_WARNING, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($infoLoggerRecord);
        $this->assertSame(TeamsPayload::COLOR_GOOD, $data['attachments'][0]['content']['body'][0]['style']);

        $data = $record->getAdaptiveCardPayload($debugLoggerRecord);
        $this->assertSame(TeamsPayload::COLOR_DEFAULT, $data['attachments'][0]['content']['body'][0]['style']);
    }

    public function testWithoutContextAndExtra()
    {
        $level = Level::Error;
        $levelName = $level->getName();
        $record = new TeamsPayload(false);
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
        $record = new TeamsPayload(true);
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
        $teamsPayload = new TeamsPayload(true);
        $data = $teamsPayload->getAdaptiveCardPayload($record);
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

        $teamsPayload = new TeamsPayload(true, false, ['context.info.library', 'extra.tags.1']);
        $data = $teamsPayload->getAdaptiveCardPayload($record);
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
