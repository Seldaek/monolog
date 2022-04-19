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

use Monolog\Level;

class SyslogHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Monolog\Handler\SyslogHandler::__construct
     */
    public function testConstruct()
    {
        $handler = new SyslogHandler('test');
        $this->assertInstanceOf('Monolog\Handler\SyslogHandler', $handler);

        $handler = new SyslogHandler('test', LOG_USER);
        $this->assertInstanceOf('Monolog\Handler\SyslogHandler', $handler);

        $handler = new SyslogHandler('test', 'user');
        $this->assertInstanceOf('Monolog\Handler\SyslogHandler', $handler);

        $handler = new SyslogHandler('test', LOG_USER, Level::Debug, true, LOG_PERROR);
        $this->assertInstanceOf('Monolog\Handler\SyslogHandler', $handler);
    }

    /**
     * @covers Monolog\Handler\SyslogHandler::__construct
     */
    public function testConstructInvalidFacility()
    {
        $this->expectException(\UnexpectedValueException::class);
        $handler = new SyslogHandler('test', 'unknown');
    }
}
