<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\TestCase;

class AmazonEC2ProcessorTest extends TestCase
{
    protected static $metadataFixture = array(
        'hostname'                      => 'ip-10-170-138-51.us-west-1.compute.internal',
        'ami-id'                        => 'ami-e78cd3a1',
        'instance-id'                   => 'i-1abfb30a',
        'instance-type'                 => 't1.micro',
        'local-ipv4'                    => '10.170.138.51',
        'public-ipv4'                   => '204.236.140.81',
        'placement/availability-zone'   => 'us-west-1c'
    );
    protected static $tmpdir;

    public static function setUpBeforeClass()
    {
        self::$tmpdir = __DIR__ . '/Fixtures/' . uniqid('AmazonEC2ProcessorTest_');
        mkdir(self::$tmpdir . '/latest/meta-data/placement', 0777, true);
        foreach (self::$metadataFixture as $key => $val) {
            file_put_contents(self::$tmpdir . '/latest/meta-data/' . $key, $val);
        }
    }

    /**
     * @covers Monolog\Processor\AmazonEC2Processor
     */
    public function testProcessor()
    {
        $base_url = "file://".self::$tmpdir;
        $processor = new AmazonEC2Processor(array(), false, $base_url);
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('hostname', $record['extra']);
        $this->assertSame('t1.micro', $record['extra']['instance-type']);
    }

    public function testProcessorCanOverrideDefaultMetadataKeys()
    {
        $base_url = "file://".self::$tmpdir;
        $processor = new AmazonEC2Processor(array('public-ipv4'), true, $base_url);
        $record = $processor($this->getRecord());
        $this->assertArrayNotHasKey('hostname', $record['extra']);
        $this->assertArrayHasKey('public-ipv4', $record['extra']);
        $this->assertSame('204.236.140.81', $record['extra']['public-ipv4']);
    }

    public function testProcessorCanMergeWithDefaultMetadataKeys()
    {
        $mac = '12:31:40:00:85:CA';
        self::$metadataFixture['mac'] = $mac;
        file_put_contents(self::$tmpdir . '/latest/meta-data/mac', $mac);

        $base_url = "file://".self::$tmpdir;
        $processor = new AmazonEC2Processor(array('mac'), false, $base_url);
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('hostname', $record['extra']);
        $this->assertArrayHasKey('public-ipv4', $record['extra']);
        $this->assertArrayHasKey('mac', $record['extra']);
        $this->assertSame($mac, $record['extra']['mac']);
    }

    public static function tearDownAfterClass()
    {
        foreach (self::$metadataFixture as $key => $val) {
            unlink(self::$tmpdir . '/latest/meta-data/' . $key);
        }
        rmdir(self::$tmpdir . '/latest/meta-data/placement');
        rmdir(self::$tmpdir . '/latest/meta-data');
        rmdir(self::$tmpdir . '/latest');
        rmdir(self::$tmpdir);
    }
}
