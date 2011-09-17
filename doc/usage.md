Using Monolog
=============

Installation
------------

To install Monolog, simply get the code (from github or through PEAR) and
configure an autoloader for the Monolog namespace.

Monolog does not provide its own autoloader but follows the PSR-0 convention,
thus allowing you to use any compatible autoloader. You could for instance use
the [Symfony2 ClassLoader component](https://github.com/symfony/ClassLoader).

Configuring a logger
--------------------

Here is a basic setup to log to a file and to firephp on the DEBUG level:

```php
<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

// Create the logger
$logger = new Logger('my_logger');
// Now add some handlers
$logger->pushHandler(new StreamHandler(__DIR__.'/my_app.log', Logger::DEBUG));
$logger->pushHandler(new FirePHPHandler());

// You can now use your logger
$logger->addInfo('My logger is now ready');
```

Let's explain it. The first step is to create the logger instance which will
be used in your code. The argument is a channel name, which is useful when
you use several loggers (see below for more details about it).

The logger itself does not know how to handle a record. It delegates it to
some handlers. The code above registers two handlers in the stack to allow
handling records in two different ways.

Note that the FirePHPHandler is called first as it is added on top of the
stack. This allows you to temporarily add a logger with bubbling disabled if
you want to override other configured loggers.

Adding extra data in the records
--------------------------------

Monolog provides two different ways to add extra informations along the simple
textual message.

### Using the logging context

The first way is the context, allowing to pass an array of data along the
record:

```php
<?php

$logger->addInfo('Adding a new user', array('username' => 'Seldaek'));
```

Simple handlers (like the StreamHandler for instance) will simply format
the array to a string but richer handlers can take advantage of the context
(FirePHP is able to display arrays in pretty way for instance).

### Using processors

The second way is to add extra data for all records by using a processor.
Processors can be any callable. They will get the record as parameter and
must return it after having eventually changed the `extra` part of it. Let's
write a processor adding some dummy data in the record:

```php
<?php

$logger->pushProcessor(function ($record) {
    $record['extra']['dummy'] = 'Hello world!';

    return $record;
});
```

Monolog provides some built-in processors that can be used in your project.
Look at the README file for the list.

> Tip: processors can also be registered on a specific handler instead of
  the logger to apply only for this handler.

Leveraging channels
-------------------

Channels are a great way to identify to which part of the application a record
is related. This is useful in big applications (and is leveraged by
MonologBundle in Symfony2). You can then easily grep through log files for
example to filter this or that type of log record.

Using different loggers with the same handlers allow to identify the logger
that issued the record (through the channel name) by keeping the same handlers
(for instance to use a single log file).

```php
<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

// Create some handlers
$stream = new StreamHandler(__DIR__.'/my_app.log', Logger::DEBUG);
$firephp = new FirePHPHandler();

// Create the main logger of the app
$logger = new Logger('my_logger');
$logger->pushHandler($stream);
$logger->pushHandler($firephp);

// Create a logger for the security-related stuff with a different channel
$securityLogger = new Logger('security');
$securityLogger->pushHandler($stream);
$securityLogger->pushHandler($firephp);
```
