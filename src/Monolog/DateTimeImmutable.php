<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use DateTimeZone;

/**
 * Overrides default json encoding of date time objects
 *
 * @author Menno Holtkamp
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DateTimeImmutable extends \DateTimeImmutable implements \JsonSerializable
{
    /**
     * @var bool
     */
    private $useMicroseconds;

    public function __construct(bool $useMicroseconds, ?DateTimeZone $timezone = null)
    {
        $this->useMicroseconds = $useMicroseconds;

        parent::__construct('now', $timezone);
    }

    public function jsonSerialize(): string
    {
        if ($this->useMicroseconds) {
            return $this->format('Y-m-d\TH:i:s.uP');
        }

        return $this->format('Y-m-d\TH:i:sP');
    }

    public function __toString(): string
    {
        return $this->jsonSerialize();
    }
}
