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

use Monolog\Logger;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Logs to a SimpleDB Domain, using Amazon's AWS SDK for PHP.
 * 
 * You'll want to create the domain in advance, more than likely, since 
 * creating new domains can take up to 10 seconds.
 * 
 * Usage example:
 * 
 * $log = new Logger('my-logging-channel');
 * $sdb = new AmazonSDB(array(
 *      // bundled, but not used by default? Weird, but true.
 *      'certificate_authority' => __DIR__.'/../vendor/amazonwebservices/aws-sdk-for-php/lib/requestcore/cacert.pem'
 *      'key' => 'AWS Access Key',
 *      'secret' => 'AWS Secret Key'
 * ));
 * $sdb->set_region(AmazonSDB::REGION_CALIFORNIA);
 * 
 * // recommended: create a SimpleDB Domain for each logging channel
 * // $sdb->create_domain('my-logging-channel');
 * 
 * $log->pushHandler(new SimpleDBHandler($sdb, Logger::WARNING));
 * 
 * @author Clay Loveless <clay@php.net>
 */
class SimpleDBHandler extends AbstractProcessingHandler
{
    
    protected $sdb;
    protected $origin_info;
    protected $onEC2;
    protected $ec2_metadata_keys = array(
        'ami-id',
        'hostname',
        'instance-id',
        'instance-type',
        'local-ipv4',
        'public-ipv4',
        'placement/availability-zone'
    );
    protected $buffer = array();
    
    
    /**
     * Pass in the SimpleDB object, the level of logging to record, the bubble-factor
     * and an optional parameter for $onEC2. If $onEC2 is true, 
     * the we'll add some extra fields about the EC2 instance you're running on.
     * This is the default behavior.
     * 
     * If you're NOT running on EC2, php_uname() hostname value will be logged.
     * 
     */
    public function __construct(\AmazonSDB $sdb, $level = Logger::DEBUG, $bubble = true, $onEC2 = true)
    {
        // batch process from here
        $sdb->batch();
        $this->sdb = $sdb;
        
        $this->onEC2 = $onEC2;
        $this->setOriginInfo();
        
        parent::__construct($level, $bubble);        
    }
    
    /**
     * If you're running this handler on EC2, the following EC2 metadata
     * key values will be stored with each log entry.
     * 
     *   hostname
     *   ami-id
     *   instance-id
     *   instance-type
     *   local-ipv4
     *   public-ipv4
     *   placement/availability-zone
     * 
     * If you want more metadata, or less, just set the keys you want instead
     * of simply passing TRUE to isEC2 in the constructor
     * 
     * Usage example:
     * 
     *$log = new Logger('my-logging-channel');
     * $sdb = new AmazonSDB(array(
     *      'certificate_authority' => __DIR__.'/../vendor/amazonwebservices/aws-sdk-for-php/lib/requestcore/cacert.pem'
     *      'key' => 'AWS Access Key',
     *      'secret' => 'AWS Secret Key'
     * ));
     * // $sdb->set_region(AmazonSDB::REGION_CALIFORNIA);
     * 
     * $sdb_handler = new SimpleDBHandler($sdb, Logger::WARNING, true, array('hostname', 'ami-id', 'kernel-id'));
     * 
     * $log->pushHandler($sdb_handler);
     * 
     */    
    protected function setOriginInfo()
    {
        $origin_info = array();
        if ($this->onEC2 !== false) {
            // should be an instant connection if on EC2
            $fp = fsockopen('169.254.169.254', 80, $errno, $errstr, 1);
            if ($fp) {
                
                if (is_array($this->onEC2)) {
                    $metadata_keys = $this->onEC2;
                } else {
                    $metadata_keys = $this->ec2_metadata_keys;
                }
                
                // since socket is already open, use it                
                foreach ($metadata_keys as $metakey) {
                    $req = "GET /latest/meta-data/{$metakey} HTTP/1.0";
                    fwrite($fp, $req);
                    $origin_info[$metakey] = stream_get_contents($fp);
                }
                fclose($fp);
                
            }
        }
        if (empty($origin_info)) {
            $origin_info = array(
                'hostname' => php_uname('n'),
                'uname-a' => php_uname('a')
            );
        }
        
        $this->origin_info = $origin_info;
    }
    
    /**
     * Buffer writes so SimpleDB BatchPutAttributes can be used
     * 
     */
    protected function write(array $record)
    {
        if ($record['level'] < $this->level) {
            return false;
        }
        
        $this->buffer[] = $record;

        return false === $this->bubble;
    }
    
    /**
     * Close handler writes to SimpleDB
     */
    public function close()
    {
        parent::close();
        
        $item_keypairs = array();
        $i = 0;
        foreach ($this->buffer as $record) {
            // generate unique ItemName
            $item_name = hash('sha256', var_export($record, true) . uniqid());
            
            $item = array(
                'datetime' => $record['datetime']->format(DATE_ISO8601),
                'level' => $record['level'],
                'message' => $record['formatted']['message']
            );
            
            // add context as first-class values
            if (! empty($record['formatted']['context'])) {
                foreach ($record['formatted']['context'] as $context_key => $context_val) {
                    $item[$context_key] = $context_val;
                }
            }
            
            // processor-added values are also first-class
            if (! empty($record['formatted']['extra'])) {
                foreach ($record['formatted']['extra'] as $extra_key => $extra_val) {
                    $item[$extra_key] = $extra_val;
                }
            }
            
            $item = array_merge($item, $this->origin_info);
            $item_keypairs[$item_name] = $item;
            $i++;
            if ($i == 25) {
                // need to start a new one
                $this->sdb->batch_put_attributes($record['channel'], $item_keypairs);
                $i = 0;
                $item_keypairs = array();
            }
        }
        $result = $this->sdb->batch_put_attributes($record['channel'], $item_keypairs);
        $result = $this->sdb->batch()->send();
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }

}