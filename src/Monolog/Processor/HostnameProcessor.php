<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

/**
 * Adds value of gethostname into the log extra
 *
 * @author Billie Thompson
 */
class HostnameProcessor
{
    public function __invoke(array $record): array
    {
        $record['extra']['hostname'] = gethostname();

        return $record;
    }
}
