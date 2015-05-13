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

/**
 * @covers Monolog\Handler\SystemdJournalHandler
 */
class SystemdJournalHandlerTest extends TestCase
{
    private function getHandlerMock($arguments)
    {
        return $this->getMock('Monolog\Handler\SystemdJournalHandler', array('checkSystemdExtension', 'sendToJournal'), $arguments);
    }

    public function testWrite()
    {
        $handler = $this->getHandlerMock(array());

        $handler->expects($this->exactly(3))
                ->method('sendToJournal')
                ->withConsecutive(
                    array(array(
                        'MESSAGE=tést1',
                        'PRIORITY=6',
                        'MONOLOG_CHANNEL=test',
                        'MONOLOG_CONTEXT_USERNAME=john'
                    )),
                    array(array(
                        'MESSAGE=tuasst2',
                        'PRIORITY=4',
                        'MONOLOG_CHANNEL=test',
                        'MONOLOG_CONTEXT_USERNAME=mary',
                        'MONOLOG_CONTEXT_FOOANDBAR_=foo'
                    )),
                    array(array(
                        'MESSAGE= My message',
                        'PRIORITY=3',
                        'MONOLOG_CHANNEL=test',
                        'MONOLOG_CONTEXT_USERNAME=rich',
                        'MONOLOG_EXTRA_THISISTHEKEY=The value',
                        'MONOLOG_EXTRA_ANOTHERKEY=Another value',
                        'MONOLOG_EXTRA_LAST_ONE=This one is well formatted',
                    ))
                );

        $handler->handle($this->getRecord(Logger::INFO, 'tést1', array('username' => 'john')));
        $handler->handle($this->getRecord(Logger::WARNING, 'tuasst2', array('username' => 'mary', '   _foo and BaR_ ' => 'foo')));

        $handler->pushProcessor(function(array $record) {
            $record['extra']['·$%$&/  This is the Key!!  '] = 'The value';
            $record['extra']['AnotherKey'] = 'Another value';
            $record['extra']['LAST_ONE'] = 'This one is well formatted';

            return $record;
        });
        $handler->handle($this->getRecord(Logger::ERROR, ' My message', array('username' => 'rich')));
    }

    public function testWriteWithExtraFields()
    {
        $handler = $this->getHandlerMock(array(
            'extraFields' => array(
                ' _?¿?¡ T  he key  ' => 'value 1',
                'FIELD2' => 'value2'
            )
        ));

        $handler->expects($this->once())
                ->method('sendToJournal')
                ->with(array(
                    'MESSAGE= My message',
                    'PRIORITY=0',
                    'MONOLOG_CHANNEL=test',
                    'MONOLOG_CONTEXT_USERNAME=rich',
                    'MONOLOG_EXTRA_THISISTHEKEY=The value',
                    'MONOLOG_EXTRA_ANOTHERKEY=Another value',
                    'MONOLOG_EXTRA_LAST_ONE=This one is well formatted',
                    'MONOLOG_HANDLEREXTRA_THEKEY=value 1',
                    'MONOLOG_HANDLEREXTRA_FIELD2=value2',
                ));

        $handler->pushProcessor(function(array $record) {
            $record['extra']['·$%$&/  This is the Key!!  '] = 'The value';
            $record['extra']['AnotherKey'] = 'Another value';
            $record['extra']['LAST_ONE'] = 'This one is well formatted';

            return $record;
        });
        $handler->handle($this->getRecord(Logger::EMERGENCY, ' My message', array('username' => 'rich')));
    }
}
