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
        static $needsMicrosecondsHack = PHP_VERSION_ID < 70100;

        $this->useMicroseconds = $useMicroseconds;
        $date = 'now';

        if ($needsMicrosecondsHack && $useMicroseconds) {
            $timestamp = microtime(true);

            // apply offset of the timezone as microtime() is always UTC
            if ($timezone && $timezone->getName() !== 'UTC') {
                $timestamp += (new \DateTime('now', $timezone))->getOffset();
            }

            // Circumvent DateTimeImmutable::createFromFormat() which always returns \DateTimeImmutable instead of `static`
            // @link https://bugs.php.net/bug.php?id=60302
            //
            // So we create a DateTime but then format it so we
            // can re-create one using the right class
            $dt = self::createFromFormat('U.u', sprintf('%.6F', $timestamp));
            $date = $dt->format('Y-m-d H:i:s.u');
        }

        parent::__construct($date, $timezone);
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
