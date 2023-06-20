<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Attribute;

use PHPUnit\Framework\TestCase;

/**
 * @requires PHP 8.0
 */
final class AsMonologProcessorTest extends TestCase
{
    public function test(): void
    {
        $asMonologProcessor = new AsMonologProcessor('channel', 'handler', 'method', -10);
        $this->assertSame('channel', $asMonologProcessor->channel);
        $this->assertSame('handler', $asMonologProcessor->handler);
        $this->assertSame('method', $asMonologProcessor->method);
        $this->assertSame(-10, $asMonologProcessor->priority);

        $asMonologProcessor = new AsMonologProcessor(null, null, null, null);
        $this->assertNull($asMonologProcessor->channel);
        $this->assertNull($asMonologProcessor->handler);
        $this->assertNull($asMonologProcessor->method);
        $this->assertNull($asMonologProcessor->priority);
    }
}
