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

class WithMonologChannelTest extends \Monolog\Test\MonologTestCase
{
    public function test(): void
    {
        $attribute = new WithMonologChannel('fixture');
        $this->assertSame('fixture', $attribute->channel);
    }
}
