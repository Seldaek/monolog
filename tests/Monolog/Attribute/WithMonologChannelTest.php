<?php

namespace Monolog\Attribute;

use PHPUnit\Framework\TestCase;

class WithMonologChannelTest extends TestCase
{
    public function test(): void
    {
        $attribute = new WithMonologChannel('fixture');
        $this->assertSame('fixture', $attribute->channel);
    }

}
