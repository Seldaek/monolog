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

use Monolog\Logger;

/**
 * Forwards records to multiple handlers
 *
 * @author Lenar Lõhmus <lenar@city.ee>
 */
class GroupHandler extends AbstractHandler
{
    protected $handlers;

    /**
     * @param Array $handlers Array of Handlers or factory callbacks($record, $fingersCrossedHandler).
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(array $handlers, $bubble = false)
    {
        $this->handlers = $handlers;
        $this->bubble = $bubble;
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
        return false === $this->bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        foreach ($this->handlers as $handler) {
            $handler->handleBatch($records);
        }
    }
 
    /**
     * Implemented to comply with the AbstractHandler requirements. Can not be called.
     */
    protected function write(array $record)
    {
        throw new \BadMethodCallException('This method should not be called directly on the GroupHandler.');
    }
}
