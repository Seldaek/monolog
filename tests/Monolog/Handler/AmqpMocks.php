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

class MockAMQPConnection extends \AMQPConnection
{
    public function __construct(){

    }

    public function pconnect()
    {
        return true;
    }

    public function reconnect()
    {
        return false;
    }

    public function connect() {
        return true;
    }

    public function isConnected() {
        return true;
    }
}


class MockAMQPChannel extends \AMQPChannel
{
    public function __construct(MockAMQPConnection $connection) {

    }
}

class MockAMQPExchange extends \AMQPExchange
{

    public function __construct(MockAMQPChannel $channel) {

    }

    public function publish($message, $routing_key, $flags, $headers){
        return;
    }

    public function setName($exchangeName) {
        return true;
    }
}