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

final class Utils
{
    /**
     * @internal
     */
    public static function getClass($object): string
    {
        $class = \get_class($object);

        return 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class;
    }

    public static function substr(string $string, int $start, ?int $length = null)
    {
        if (extension_loaded('mbstring')) {
            return mb_strcut($string, $start, $length);
        }

        return substr($string, $start, $length);
    }
}
