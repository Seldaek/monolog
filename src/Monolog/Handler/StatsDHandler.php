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

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\StatsDFormatter;
use Monolog\Logger;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use Liuggio\StatsdClient\Factory\StatsdDataFactory;
use Liuggio\StatsdClient\StatsdClientInterface;

/**
 * A processing handler for StatsD.
 *
 * @author Giulio De Donato <liuggio@gmail.com>
 */
class StatsDHandler extends AbstractProcessingHandler
{
    /**
     * @var array
     */
    protected $buffer = array();

     /**
     * @var string
     */
    protected $prefix;

    /**
     * @var statsDService
     */
    protected $statsDService;

    /**
     * @var statsDFactory
     */
    protected $statsDFactory;

    /**
     * @param StatsdClientInterface      $statsDService The Service sends the packet
     * @param StatsdDataFactoryInterface $statsDFactory The Factory creates the StatsDPacket
     * @param string                     $prefix        Statsd key prefix
     * @param integer                    $level         The minimum logging level at which this handler will be triggered
     * @param Boolean                    $bubble        Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(StatsdClientInterface $statsDService, StatsdDataFactoryInterface $statsDFactory  = null, $prefix, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->statsDService = $statsDService;
        $this->statsDFactory = $statsDFactory ? $statsDFactory : new StatsdDataFactory();
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->statsDService->send($this->buffer);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    { 
        $records = is_array($record['formatted']) ? $record['formatted'] : array($record['formatted']);

        foreach ($records as $record) {
            if (!empty($record)) {
                $this->buffer[] = $this->statsDFactory->increment(sprintf('%s.%s', $this->getPrefix(), $record));
            }
        }
    }
 
    /**
     * Gets the default formatter.
     *
     * @return FormatterInterface
     */
    protected function getDefaultFormatter()
    {
        return new StatsDFormatter();
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param StatsdClientInterface $statsDService
     */
    public function setStatsDService(StatsdClientInterface $statsDService)
    {
        $this->statsDService = $statsDService;
    }

    /**
     * @param StatsdDataFactoryInterface $statsDFactory
     */
    public function setStatsDFactory(StatsdDataFactoryInterface $statsDFactory)
    {
        $this->statsDFactory = $statsDFactory;
    }
}
