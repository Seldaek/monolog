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
 * Overrides default json encoding of date time objects
 *
 * @author Menno Holtkamp
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DateTimeImmutable extends \DateTimeImmutable implements \JsonSerializable
{
    private $useMicroseconds;

    public function __construct($useMicroseconds, \DateTimeZone $timezone = null)
    {
        $date = null;
        if ($useMicroseconds) {
            $timestamp = microtime(true);
            $microseconds = sprintf("%06d", ($timestamp - floor($timestamp)) * 1000000);
            $date = date('Y-m-d H:i:s.' . $microseconds, $timestamp);
        }
        parent::__construct($date, $timezone);

        $this->useMicroseconds = $useMicroseconds;
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        if ($this->useMicroseconds) {
            return $this->format('Y-m-d\TH:i:s.uP');
        }

        return $this->format('Y-m-d\TH:i:sP');
    }
}
