<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

/**
 * Formats data into an associative array of scalar values.
 * Objects and arrays will be JSON encoded.
 *
 * @author Andrew Lawson <adlawson@gmail.com>
 */
class ScalarFormatter extends NormalizerFormatter
{
    /**
     * {@inheritDoc}
     *
     * @phpstan-return array<string, scalar|null> $record
     */
    public function format(array $record): array
    {
        $result = [];
        foreach ($record as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * @param  mixed                      $value
     * @return scalar|null
     */
    protected function normalizeValue($value)
    {
        $normalized = $this->normalize($value);

        if (is_array($normalized)) {
            return $this->toJson($normalized, true);
        }

        return $normalized;
    }
}
