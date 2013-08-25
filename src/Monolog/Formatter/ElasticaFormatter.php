<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Elastica\Document;

/**
 * Format a log message into an Elastica Document
 *
 * @author Jelle Vink <jelle.vink@gmail.com>
 */
class ElasticaFormatter extends NormalizerFormatter
{
    /**
     * @var string Elastic search index name
     */
    protected $esIndex;

    /**
     * @var string Elastic search document type
     */
    protected $esType;

    /**
     * @param string $index Elastic Search index name
     * @param string $type  Elastic Search document type
     */
    public function __construct($esIndex, $esType)
    {
        parent::__construct('c'); // ISO 8601 date format
        $this->esIndex = $esIndex;
        $this->esType = $esType;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);
        return $this->getDocument($this->esIndex, $this->esType, $record);
    }

    /**
     * Getter EsIndex
     * @return string
     */
    public function getEsIndex()
    {
        return $this->esIndex;
    }

    /**
     * Getter EsType
     * @return string
     */
    public function getEsType()
    {
        return $this->esType;
    }

    /**
     * Convert a log message into an Elastica Document
     *
     * @param string $index  Elastic Search index name
     * @param string $type   Elastic Search document type
     * @param array  $record Log message
     * @return \Elastica\Document
     */
    protected function getDocument($index, $type, $record)
    {
        $document = new Document();
        $document->setData($record);
        $document->setType($type);
        $document->setIndex($index);
        return $document;
    }
}
