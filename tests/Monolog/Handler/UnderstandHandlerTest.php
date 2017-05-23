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

class UnderstandHandlerTest extends TestCase
{
    public function testHandler()
    {
        $inputKey = 'input-key';
     
        $constructorArgs = array($inputKey);
        
        $this->handler = $this->getMock(
            '\Monolog\Handler\UnderstandHandler',
            array('exec'),
            $constructorArgs
        );

        $cmd = null;
        
        $this->handler->expects($this->any())
        ->method('exec')
        ->will($this->returnCallback(function($curlCall) use(&$cmd)
        {
            $cmd = $curlCall;
        }));
        
        $record = $this->getRecord();
        $this->handler->handle($record);

        $this->assertNotEmpty($cmd);
        $this->assertContains('curl -X POST -d', $cmd);
        $this->assertContains(implode(' ', array('https://api.understand.io/' . $inputKey, '> /dev/null 2>&1 &')), $cmd);   
    }
}
