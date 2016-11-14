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

    public function testStringifyReturnsNullWithNoLineFormatter()
    {
        $slackRecord = new SlackRecord('#test');
        $this->assertNull($slackRecord->stringify(array('foo' => 'bar')));
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
}
