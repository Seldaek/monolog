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

/**
 * Class DingtalkBotHandlerTest
 *
 * @author Yarco Wang <yarco.wang@gmail.com>
 * @since 7/23/17 10:11 AM
 * @see https://open-doc.dingtalk.com/docs/doc.htm?spm=a219a.7629140.0.0.CXUyDQ&treeId=257&articleId=105735&docType=1
 * @coversDefaultClass Monolog\Handler\DingtalkBotHandler
 */
class DingtalkBotHandlerTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testConstructorMinimal()
    {
        $handler = new DingtalkBotHandler('test-token', 'test-title');
        $this->assertInstanceOf('Monolog\Handler\AbstractProcessingHandler', $handler);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorFull()
    {
        $handler = new DingtalkBotHandler(
            'test-token',
            'test-title',
            Logger::DEBUG,
            false
        );
        $this->assertInstanceOf('Monolog\Handler\AbstractProcessingHandler', $handler);
    }
}