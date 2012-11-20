<?php

require __DIR__ . "/../../vendor/autoload.php";

$connection = stream_socket_client("udp://127.0.0.1:1113", $errno, $errstr);

$logger = new \Monolog\Logger("udp");
$logger->pushHandler(new \Monolog\Handler\UdpHandler($connection));

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