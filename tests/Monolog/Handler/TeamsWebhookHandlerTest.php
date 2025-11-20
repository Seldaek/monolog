<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Handler;

use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\Teams\TeamsRecord;
use Monolog\Handler\TeamsWebhookHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TeamsWebhookHandler::class)]
class TeamsWebhookHandlerTest extends \Monolog\Test\MonologTestCase
{
    const WEBHOOK_URL = 'https://monolog.webhook.office.com/webhookb2/b4c2e6a1-9f13-4db3-92ae-7a210b1d52e9@3d87f6b4-2c4a-44ea-95cd-1feaf83b7a61/IncomingWebhook/a91f4c3e8d2b46d28e7a32f9b508c4fa/ef72c8d1-3baf-4e58-b2a1-9c4f83d7e2b5/K7xQm1RdWvN9tG4Pa2LbF8ySzCwJr6HeXpR0BuViYaTqLk';

    /**
     * @covers ::__construct
     * @covers ::getTeamsRecord
     */
    public function testConstructorMinimal()
    {
        $handler = new TeamsWebhookHandler(self::WEBHOOK_URL);
        $record = $this->getRecord();
        $teamsRecord = $handler->getTeamsRecord();
        $this->assertInstanceOf('Monolog\Handler\Teams\TeamsRecord', $teamsRecord);
        $this->assertEquals([
            'type'        => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content'     => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type'    => 'AdaptiveCard',
                        'version' => '1.5',
                        'body'    => [
                            [
                                'type'  => 'Container',
                                'style' => TeamsRecord::COLOR_WARNING,
                                'items' => [
                                    [
                                        'type'   => 'TextBlock',
                                        'text'   => 'test',
                                        'weight' => 'Bolder',
                                        'size'   => 'Medium',
                                        'wrap'   => true,
                                    ],
                                ],
                            ],
                            [
                                'type'    => 'Container',
                                'spacing' => 'Medium',
                                'items'   => [
                                    [
                                        'type'  => 'FactSet',
                                        'facts' => [
                                            [
                                                'title' => 'Level',
                                                'value' => Level::Warning->getName(),
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        ],
                        'actions' => [],
                    ],
                ],
            ],
        ], $teamsRecord->getAdaptiveCardPayload($record));
    }

    /**
     * @covers ::__construct
     * @covers ::getTeamsRecord
     */
    public function testConstructorFull()
    {
        $handler = new TeamsWebhookHandler(
            self::WEBHOOK_URL,
            true,
            Level::Warning,
            false
        );

        $record = $this->getRecord();
        $teamsRecord = $handler->getTeamsRecord();
        $this->assertInstanceOf('Monolog\Handler\Teams\TeamsRecord', $teamsRecord);
        $this->assertEquals([
            'type'        => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content'     => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type'    => 'AdaptiveCard',
                        'version' => '1.5',
                        'body'    => [
                            [
                                'type'  => 'Container',
                                'style' => TeamsRecord::COLOR_WARNING,
                                'items' => [
                                    [
                                        'type'   => 'TextBlock',
                                        'text'   => 'test',
                                        'weight' => 'Bolder',
                                        'size'   => 'Medium',
                                        'wrap'   => true,
                                    ],
                                ],
                            ],
                            [
                                'type'    => 'Container',
                                'spacing' => 'Medium',
                                'items'   => [
                                    [
                                        'type'  => 'FactSet',
                                        'facts' => [
                                            [
                                                'title' => 'Level',
                                                'value' => Level::Warning->getName(),
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        ],
                        'actions' => [],
                    ],
                ],
            ],
        ], $teamsRecord->getAdaptiveCardPayload($record));
    }

    /**
     * @covers ::getFormatter
     */
    public function testGetFormatter()
    {
        $handler = new TeamsWebhookHandler(self::WEBHOOK_URL);
        $formatter = $handler->getFormatter();
        $this->assertInstanceOf('Monolog\Formatter\FormatterInterface', $formatter);
    }

    /**
     * @covers ::setFormatter
     */
    public function testSetFormatter()
    {
        $handler = new TeamsWebhookHandler(self::WEBHOOK_URL);
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);
        $this->assertSame($formatter, $handler->getFormatter());
    }
}
