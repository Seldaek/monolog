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

class AmqpExchangeMock extends \AMQPExchange
{
    protected $messages = array();

    public function __construct()
    {
    }

    public function publish($message, $routing_key, $params = 0, $attributes = array())
    {
        $this->messages[] = array($message, $routing_key, $params, $attributes);

        return true;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function setName($name)
    {
        return true;
    }
}
