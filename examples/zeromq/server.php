<?php
/**
 * Created by JetBrains PhpStorm.
 * User: hissterkiller
 * Date: 11/15/12
 * Time: 8:37 PM
 * To change this template use File | Settings | File Templates.
 */
$context = new ZMQContext();
$socket  = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$socket->bind("tcp://*:5555");
$totalMessage = 0;
while (true) {

    usleep(100);

    try {

        $message = $socket->recv(ZMQ::MODE_NOBLOCK);

        if (empty($message)) {
            continue;
        }
        echo $totalMessage++ . PHP_EOL;
        echo $message . PHP_EOL;

    } catch (\Exception $error) {
        var_dump($error);
    }
}
