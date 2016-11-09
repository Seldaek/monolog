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

use Monolog\Formatter\NormalizerFormatter;

/**
 * Stores to a csv file
 *
 * Can be used to store big loads to physical files and import them later into another system that can handle CSV
 *
 * @author Jay MOULIN <jaymoulin@gmail.com>
 */
class CsvHandler extends StreamHandler
{
    const DELIMITER = ',';
    const ENCLOSURE = '\'';
    const ESCAPE_CHAR = '\\';

    /**
     * @inheritdoc
     */
    protected function streamWrite($resource, $formatted)
    {
        return fputcsv($resource, (array)$formatted, static::DELIMITER, static::ENCLOSURE, static::ESCAPE_CHAR);
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }
}
