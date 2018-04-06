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
 * Format a log message into an ElasticSearch record
 *
 * @author Avtandil Kikabidze <akalongman@gmail.com>
 */
class ElasticSearchFormatter extends NormalizerFormatter
{
    /**
     * @var string ElasticSearch index name
     */
    protected $index;

    /**
     * @var string ElasticSearch record type
     */
    protected $type;

    /**
     * @param string $index ElasticSearch index name
     * @param string $type ElasticSearch record type
     */
    public function __construct($index, $type)
    {
        // ElasticSearch requires an ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->index = $index;
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

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
     * Convert a log message into an ElasticSearch record
     *
     * @param  array $record Log message
     * @return array
     */
    protected function getDocument($record): array
    {
        $record['_index'] = $this->index;
        $record['_type'] = $this->type;

        return $record;
    }
}
