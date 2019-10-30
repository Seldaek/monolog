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

use DateTime;

/**
 * Format a log message into an Elasticsearch record
 *
 * @author Avtandil Kikabidze <akalongman@gmail.com>
 */
class ElasticsearchFormatter extends NormalizerFormatter
{
    /**
     * @var string Elasticsearch index name
     */
    protected $index;

    /**
     * @var string Elasticsearch record type
     */
    protected $type;

    /**
     * @param string $index Elasticsearch index name
     * @param string $type  Elasticsearch record type
     */
    public function __construct(string $index, string $type)
    {
        // Elasticsearch requires an ISO 8601 format date with optional millisecond precision.
        parent::__construct(DateTime::ISO8601);

        $this->index = $index;
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        if ($this->recordHasContext($record) && $this->contextHasException($record['context'])) {
            $record['context']['exception'] = $this->getContextException($record['context']['exception']);
        }

        return $this->getDocument($record);
    }

    /**
     * Getter index
     *
     * @return string
     */
    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * Getter type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Convert a log message into an Elasticsearch record
     *
     * @param  array $record Log message
     * @return array
     */
    protected function getDocument(array $record): array
    {
        $record['_index'] = $this->index;
        $record['_type'] = $this->type;

        return $record;
    }

    /**
     * Returns the entire exception as Elasticsearch format
     *
     * @param  array $recordContext
     *
     * @return array
     */
    protected function getContextException(array $recordContext)
    {
        return [
            'class'     => $recordContext['class'] ?? '',
            'message'   => $recordContext['message'] ?? '',
            'code'      => intval($recordContext['code']) ?? '',
            'file'      => $recordContext['file'] ?? '',
            'trace'     => $recordContext['trace'] ?? '',
        ];
    }

    /**
     * Identifies the content type of the given $record
     *
     * @param  array $record
     *
     * @return bool
     */
    protected function recordHasContext(array $record): bool
    {
        return (
            array_key_exists('context', $record)
        );
    }

    /**
     * Identifies the content type of the given $context
     *
     * @param  mixed $context
     *
     * @return bool
     */
    protected function contextHasException(array $context): bool
    {
        return (
            is_array($context)
            && array_key_exists('exception', $context)
        );
    }
}
