<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Vladimir Belozyorov <vladimir.belozyorov@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;
use GearmanClient;

class GearmanHandler extends AbstractProcessingHandler
{
    /**
     *
     * @var GearmanClient
     */
    protected $client;

    /**
     *
     * @var string Allow to prefix all loggers e.g. with project alias, before sending job to Gearman server
     */
    protected $prefix = '';

    /**
     * @param GearmanClient            $client
     * @param int                      $level
     * @param bool                     $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(GearmanClient $client, $level = Logger::DEBUG, $bubble = true)
    {
        $this->client = $client;

        parent::__construct($level, $bubble);
    }

    /**
     *
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $data = $record["formatted"];

        $function_name = sprintf("%s.%s", $record['channel'], $record['level_name']);
        if ($this->prefix) {
            $function_name = "{$this->prefix}.{$function_name}";
        }

        $this->client->doBackground($function_name, $data);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
    }
}
