Sockets Handler
===============

This handler allows you to write your logs to sockets using [fsockopen](http://php.net/fsockopen)
or [pfsockopen](http://php.net/pfsockopen).

Basic Example
-------------

This example e persistent connections:

```php
<?php

use Monolog\Logger;
use Monolog\Handler\SocketHandler;
use Monolog\Handler\SocketHandler\Socket;

// Create the logger
$logger = new Logger('my_logger');

// Create the handler
$handler = new SocketHandler('unix:///var/log/httpd_app_log.socket');

// Now add the handler
$logger->pushHandler($handler, Logger::DEBUG);

// You can now use your logger
$logger->addInfo('My logger is now ready');

```

In this example, using syslog-ng, you should see the log on the log server:

    cweb1 [2012-02-26 00:12:03] my_logger.INFO: My logger is now ready [] [] 


Symfony2 Example
----------------

In Symfony2, first we have to create the handler service in our services.xml (or similar):

```xml
        <!-- Configure our socket -->
        <service id="logging.socket"
                 class="Monolog\Handler\SocketHandler\PersistentSocket"
                 public="false">
            <argument>%logging.socket.connection_string%</argument>
            <call method="setTimeout">
              <argument>2</argument>
            </call>
            <call method="setConnectionTimeout">
              <argument>2</argument>
            </call>
        </service>

        <!-- Create our handler and inject the socket -->
        <service id="logging.socket_handler" class="Monolog\Handler\SocketHandler">
            <argument></argument>
            <call method="setSocket">
                <argument type="service" id="logging.socket"/>
            </call>
        </service>
```

And then, change our config.yml (or similar):

```yaml
parameters:
    logging.socket.connection_string:  'unix:///var/log/httpd_app_log.socket'

monolog:
    handlers:
        main:
            type:  stream
            path:  %kernel.logs_dir%/%kernel.environment%.log
            level: debug
        firephp:
            type:  firephp
            level: info
        custom:
            type:  service
            id:    logging.socket_handler
```
