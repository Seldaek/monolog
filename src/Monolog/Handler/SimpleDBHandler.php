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
 * You'll probably want to create the domain in advance, since 
 * creating new domains can take up to 10 seconds. 
 * 
 * However, creating SimpleDB domains is an idempotent operation; running it 
 * multiple times using the same domain name will not result in an error 
 * response. So, we make the request once per object instantiation anyway,
 * just to minimize likelihood of missing log events.
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
    protected $skip_create;
    protected $known_channels = array();    
    
    /**
     * Pass in the SimpleDB object, the level of logging to record, the bubble-factor
     * and an optional parameter for $onEC2. If $onEC2 is true, 
     * the we'll add some extra fields about the EC2 instance you're running on.
     * This is the default behavior.
     * 
     * If you're NOT running on EC2, php_uname() hostname value will be logged.
     * 
     * @param object  $sdb          AmazonSDB object
     * @param integer $level        Logging level
     * @param boolean $bubble       Whether the messages that are handled can bubble up the stack or not
     * @param boolean $onEC2        Whether the environment is an EC2 instance or not
     * @param boolean $skip_create  Whether or not to send the create_domain command for the log channel
     */
    public function __construct(\AmazonSDB $sdb, $level = Logger::DEBUG, $bubble = true, $onEC2 = true, $skip_create = false)
    {
        $this->sdb = $sdb;
        
        $this->onEC2 = (bool) $onEC2;
        $this->skip_create = (bool) $skip_create;
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
     * $log = new Logger('my-logging-channel');
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
     * @return void
     */ 
    protected function setOriginInfo()
    {
        $origin_info = array();
        if ($this->onEC2 !== false) {
            // should be an instant connection if on EC2, so timeout after
            // one second to minimize delay for anyone who forgot to use 
            // the setting.
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
            } else {
                // log a warning in hopes they'll notice and fix their settings
                trigger_error(
                    __CLASS__.': Pass appropriate onEC2 flag to save 1 second on instantiation',
                    E_USER_NOTICE
                );
            }
        }
        if (empty($origin_info)) {
            $origin_info = array(
                'hostname' => php_uname('n')
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
        
        // send the domain create command for the channel, if necessary
        if (! $this->skip_create) {
            if (! in_array($record['channel'], $this->known_channels)) {
                $this->sdb->create_domain($record['channel']);
                $this->known_channels[] = $record['channel'];
            }
        }
        
        $item_name = $this->getLogItemName();
        
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

        $this->sdb->put_attributes($record['channel'], $item_name, $item);

        return false === $this->bubble;
    }
    
    /**
     * Create a UUID without relying on ext/uuid
     * 
     * @return string
     */
    protected function getLogItemName()
    {
        /**
         * Thanks to Andrew Moore.
         * @see http://www.php.net/manual/en/function.uniqid.php#94959
         */
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            
            // 16 bits for "time_hi_and_version"
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }

}