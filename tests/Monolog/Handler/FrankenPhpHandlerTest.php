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

class FrankenPhpHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Handler\FrankenPhpHandler::__construct
     */
    public function testConstructorThrowsWhenFrankenPhpIsNotAvailable()
    {
        $this->expectException(MissingExtensionException::class);

        new FrankenPhpHandler();
    }

    /**
     * @covers Monolog\Handler\FrankenPhpHandler::getDefaultFormatter
     */
    public function testGetDefaultFormatterReturnsNormalizerFormatter()
    {
        $handler = (new \ReflectionClass(FrankenPhpHandler::class))->newInstanceWithoutConstructor();

        $this->assertInstanceOf('Monolog\Formatter\NormalizerFormatter', $handler->getDefaultFormatter());
    }
}
