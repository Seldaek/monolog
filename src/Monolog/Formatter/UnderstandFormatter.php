<?php 

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;

/**
 * Formats record into JSON in a format compatible with Understand.io API.
 *
 * @author Aivis Silins <aivis.silins@gmail.com>
 */
class UnderstandFormatter extends JsonFormatter implements FormatterInterface
{

    /**
     * @param integer $batchMode
     * @param boolean $appendNewline
     */
    public function __construct($batchMode = self::BATCH_MODE_JSON, $appendNewline = false)
    {
        parent::__construct($batchMode, $appendNewline);
    }
    
    /**
     * Format record
     *
     * @param array $record
     * @return string
     */
    public function format(array $record)
    {
        $recordWithTimestamp = $this->convertDatetime($record);

        return parent::format($recordWithTimestamp);
    }

    /**
     * Format batch of records
     *
     * @param array $records
     * @return string
     */
    public function formatBatch(array $records)
    {
        $batch = array();

        foreach($records as $record)
        {
            $batch[] = $this->convertDatetime($record);
        }

        return parent::format($batch);
    }

    /**
     * Convert datetime to timestamp format (milliseconds)
     *
     * @param array $record
     * @return array
     */
    protected function convertDatetime(array $record)
    {
        if (isset($record['datetime']) && $record['datetime'] instanceof \DateTime)
        {
            // U - Seconds since the Unix Epoch
            // u - Microseconds (added in PHP 5.2.2). Note that date() will always generate 000000
            // http://php.net/manual/en/function.date.php
            $record['timestamp'] = intval(round((float)$record['datetime']->format('U.u') * 1000));

            unset($record['datetime']);
        }

        return $record;
    }
}
