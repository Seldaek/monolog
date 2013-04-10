<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\TestCase;
use Monolog\Logger;

use Liuggio\StatsdClient\Entity\StatsdData;


class StatsDHandlerTest extends TestCase
{
    protected function setup()
    {
        if (!interface_exists('Liuggio\StatsdClient\StatsdClientInterface')) {
            $this->markTestSkipped('The "liuggio/statsd-php-client" package is not installed');
        }
    }

    public function testHandle()
    {
        $client = $this->getMock('Liuggio\StatsdClient\StatsdClientInterface');
        $factory = $this->getMock('Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface');
        
        $factory->expects($this->any())
            ->method('increment')
            ->will($this->returnCallback(function ($input){
                return sprintf('%s|c|1', $input);
        }));

        $prefixToAssert = 'prefix';
        $messageToAssert = 'test-msg';

        $record = $this->getRecord(Logger::WARNING, $messageToAssert, array('data' => new \stdClass, 'foo' => 34));
 
        $assert = array(sprintf('%s.test.WARNING.%s|c|1',$prefixToAssert, $messageToAssert), 
            sprintf('%s.test.WARNING.%s.context.data.[object] (stdClass: {})|c|1',$prefixToAssert, $messageToAssert),
            sprintf('%s.test.WARNING.%s.context.foo.34|c|1',$prefixToAssert, $messageToAssert));
 
        $client->expects($this->once())
            ->method('send')
            ->with($assert);

        $handler = new StatsDHandler($client, $factory, $prefixToAssert);
        $handler->handle($record);
    }
}
