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
use Monolog\Handler\SimpleDBHandler;

class SimpleDBHandlerTest extends TestCase
{
    protected static $sdb;
    protected static $channel;

    public static function setUpBeforeClass()
    {
        if (! defined('MONOLOG_AWS_SDB_KEY')) {
            return;
        }

        static::$sdb = new \AmazonSDB(array(
            'key' => MONOLOG_AWS_SDB_KEY,
            'secret' => MONOLOG_AWS_SDB_SECRET,
            'certificate_authority' => __DIR__.'/../../../vendor/amazonwebservices/aws-sdk-for-php/lib/requestcore/cacert.pem'
        ));
        static::$sdb->set_region(MONOLOG_SDB_REGION);

        static::$channel = uniqid('monolog_phpunit_events_');
    }

    public function setUp()
    {
        if (! class_exists('\AmazonSDB')) {
            $this->markTestSkipped('amazonwebservices/aws-sdk-for-php not installed');
        }
        if (! defined('MONOLOG_AWS_SDB_KEY')) {
            $this->markTestSkipped('SimpleDB constants are not set in phpunit.xml');
        }

        if (empty(static::$channel)) {
            $this->markTestSkipped('Gave up waiting on test domain creation');
        }

        // do we need to create the test domain?
        $domains = static::$sdb->get_domain_list();

        if (! in_array(static::$channel, $domains)) {
            $result = static::$sdb->create_domain(static::$channel);

            $channelFound = false;
            $waitMax = 20;
            $wait = 0;
            while (! $channelFound) {
                // wait awhile
                sleep(1);
                $wait++;
                $domains = static::$sdb->get_domain_list();
                if (in_array(static::$channel, $domains)) {
                    $channelFound = true;
                }
                if ($wait == $waitMax) {
                    static::$channel = null;
                    $this->markTestSkipped("Waited {$wait_max} seconds for test domain creation");
                    break;
                }
            }
        }

    }

    public function testConstruct()
    {
        $handler = $this->getHandler();
        $this->assertInstanceOf('Monolog\Handler\SimpleDBHandler', $handler);
    }

    public function testDebug()
    {
        $handler = $this->getHandler();

        $record = $this->getRecord(Logger::DEBUG, "A testDebug message");
        $record['channel'] = static::$channel;
        $handler->handle($record);

        // account for eventual consistency
        sleep(3);

        $result = static::$sdb->select("SELECT * FROM ".static::$channel." WHERE level='".Logger::DEBUG."'");
        $msg = null;
        foreach ($result->body->SelectResult->Item->Attribute as $att) {
            if ($att->Name == 'message') {
                $msg = (string) $att->Value;
            }
        }

        $this->assertSame('A testDebug message', $msg);
    }

    protected function getHandler()
    {
        return new SimpleDBHandler(static::$sdb, Logger::DEBUG, true, false);
    }

    public static function tearDownAfterClass()
    {
        if (! empty(static::$sdb)) {
            static::$sdb->delete_domain(static::$channel);
        }
    }
}
