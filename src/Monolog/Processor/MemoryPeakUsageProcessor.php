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
 * Injects memory_get_peak_usage in all records
 *
 * @see Monolog\Processor\MemoryProcessor::__construct() for options
 * @author Rob Jensen
 */
class MemoryPeakUsageProcessor extends MemoryProcessor
{
    public function __invoke(array $record): array
    {
        $bytes = memory_get_peak_usage($this->realUsage);
        $formatted = $this->formatBytes($bytes);

        $record['extra']['memory_peak_usage'] = $formatted;

        return $record;
    }
}
