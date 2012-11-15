<?php
/**
 * Created by JetBrains PhpStorm.
 * User: hissterkiller
 * Date: 11/15/12
 * Time: 8:34 PM
 * To change this template use File | Settings | File Templates.
 */

require __DIR__ . "/../../vendor/autoload.php";

$connection = new \ZMQSocket(new \ZMQContext(), \ZMQ::SOCKET_PUSH, "monolog");
$connection->connect("tcp://127.0.0.1:5555");

$logger = new \Monolog\Logger("zeromq");
$logger->pushHandler(new \Monolog\Handler\ZeroMQHandler($connection));

$logger->err(
    "1232332",
    array(
        "id"     => 1,
        "params" => array(
            array(
                "message"  => "This is bullshit to.",
                "created"  => time(),
                "someData" => mt_rand(0, 49344409875093475),
                "user"     => array(
                    "firstname" => "Francis",
                    "lastname"  => "Varga",
                    "name"      => "Francis Varga",
                    "address"   => "foobar street 1234567890 Berlin",
                    "bio"       => "Awesome shit",
                    "email"     => "foobar[at]barfoo.com",
                ),
            )
        )
    )
);