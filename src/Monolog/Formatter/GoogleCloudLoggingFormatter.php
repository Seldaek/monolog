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

use DateTimeInterface;
use Monolog\LogRecord;

/**
 * Encodes message information into JSON in a format compatible with Cloud logging.
 *
 * @see https://cloud.google.com/logging/docs/structured-logging
 * @see https://cloud.google.com/logging/docs/reference/v2/rest/v2/LogEntry
 *
 * @author Luís Cobucci <lcobucci@gmail.com>
 */
final class GoogleCloudLoggingFormatter extends JsonFormatter
{
    const CONTEXT_HEADER_FORMAT = '/([0-9a-fA-F]{32})(?:\/(\d+))?(?:;o=(\d+))?/';

    private static ?string $traceID = null;

    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        // Re-key level for GCP logging
        $normalized['severity'] = $normalized['level_name'];
        $normalized['time'] = $record->datetime->format(DateTimeInterface::RFC3339_EXTENDED);

        // Tag with Trace ID for request attribution
        $normalized['logging.googleapis.com/trace'] = $this->getTraceID();

        // Remove keys that are not used by GCP
        unset($normalized['level'], $normalized['level_name'], $normalized['datetime']);

        return $normalized;
    }

    private function getTraceID(): ?string
    {
        if (empty($this->traceID) && !empty($_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'])) {
            $matched = preg_match(
                self::CONTEXT_HEADER_FORMAT,
                $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? '',
                $matches,
            );

            if (!$matched) {
                return null;
            }

            $projectID = $this->getProjectID();
            if (empty($projectID)) {
                return null;
            }

            $this->traceID = 'projects/'.$projectID.'/traces/'.strtolower($matches[1]);
        }

        return $this->traceID;
    }

    private function getProjectID(): ?string
    {
        if (isset($_SERVER['GOOGLE_CLOUD_PROJECT'])) {
            return $_SERVER['GOOGLE_CLOUD_PROJECT'];
        }

        if (class_exists('\Google\Cloud\Core\Compute\Metadata')) {
            return (new \Google\Cloud\Core\Compute\Metadata())->getProjectId();
        }

        return null;
    }
}
