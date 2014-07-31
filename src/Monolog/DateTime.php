<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

/**
 * Add microsecond detail to JSON encoded DateTime objects
 *
 * @author Menno Holtkamp
 */
class DateTime extends \DateTime implements \JsonSerializable
{

    /**
     * Override function to ensure an object of this class is returned
     *
     * @link http://stackoverflow.com/questions/17909871/getting-date-format-m-d-y-his-u-from-milliseconds
     * @param string $format
     * @param string $time
     * @param DateTimeZone $timezone
     * @return static
     */
    public static function createFromFormat($format, $time, $timezone = null)
    {
        $timestamp = microtime(true);
        $microseconds = sprintf('%06d', ($timestamp - floor($timestamp)) * 1000000);

        return new static(date('Y-m-d H:i:s.' . $microseconds, $timestamp));
    }

    /**
     * Append the microseconds to the date property
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $result = (array)$this;
        $result['date'] .= $this->format('.u');

        return $result;

    }

}
