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
 * response.
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
    protected $skipCreate;
    protected $knownChannels = array();

    /**
     * Pass in the SimpleDB object, the level of logging to record, the bubble-factor
     * and an optional $skip_create parameter to bypass attempts to create
     * the SimpleDB domain.
     *
     * @param object  $sdb         AmazonSDB object
     * @param integer $level       Logging level
     * @param boolean $bubble      Whether the messages that are handled can bubble up the stack or not
     * @param boolean $skip_create Whether or not to send the create_domain command for the log channel
     */
    public function __construct(\AmazonSDB $sdb, $level = Logger::DEBUG, $bubble = true, $skip_create = false)
    {
        $this->sdb = $sdb;

        $this->skipCreate = (bool) $skip_create;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        // send the domain create command for the channel, if necessary
        if (!$this->skipCreate) {
            if (!in_array($record['channel'], $this->knownChannels)) {
                $this->sdb->create_domain($record['channel']);
                $this->knownChannels[] = $record['channel'];
            }
        }

        $item_name = $this->getLogItemName();

        $item = array(
            'datetime' => $record['datetime']->format(DATE_ISO8601),
            'level' => $record['level'],
            'message' => $record['formatted']['message']
        );

        // add 'context' and 'extra' as first-class values
        $item = array_merge(
            $item,
            $record['formatted']['context'],
            $record['formatted']['extra']
        );

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
