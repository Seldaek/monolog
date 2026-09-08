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

    public function testOnConstructorParameter(): void
    {
        $parameter = new \ReflectionParameter([WithMonologChannelOnParameterFixture::class, '__construct'], 'logger');
        $attributes = $parameter->getAttributes(WithMonologChannel::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('fixture', $attributes[0]->newInstance()->channel);
    }
}

final class WithMonologChannelOnParameterFixture
{
    public function __construct(
        #[WithMonologChannel('fixture')]
        public readonly \stdClass $logger,
    ) {
    }
}
