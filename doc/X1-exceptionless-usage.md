monolog-exceptionless
=============

Exceptionless Handler for Monolog, which allows to send log messages to Exceptionless instance.

# Disclaimer
This is a simple handler for monolog. This version works for custom development, on high traffic sites is recommended to update the curl process itu use as async. 

# Installation
monolog-exceptionless is included with the monolog installation.

# Usage
Just use it as any other Monolog Handler, push it to the stack of your Monolog Logger instance. The Handler however needs some parameters:

- **$token** This is a API key you register in ExceptionLess server.
- **hosturi** The server Url where ExceptionLess is; the default is hosted ExceptionLess is at: https://www.exceptionless.io
- **$level** can be any of the standard Monolog logging levels. Use Monologs statically defined contexts. _Defaults to Logger::DEBUG_
- **$bubble** _Defaults to true_

# Examples

```php
//Import class
use Monolog\Logger;
use Monolog\Handler\ExceptionLessHandler;

//Create logger
$logger = new Logger('my_logger');
$exceptionless = new ExceptionLessHandler('3V52Gdqux6gRWEWTGrthfgdBW7QU8AscOgaP','https://www.exceptionless.io', Logger::INFO);

//You can set up proxy configuration:
//$exceptionless->setProxy('<proxy addr>:<port>', '<username>:<password>');

$logger->pushHandler($exceptionless);

//Now you can use the logger.
$logger->addWarning("This is a great message, woohoo!");

```

Example to use as an extension of [BooBoo](https://github.com/thephpleague/booboo) Error logger.

Assuming you install booboo and monolog.

```bash
composer require league/booboo
composer require monolog/monolog
```

Now in your php file.

```php

//Import class
use Monolog\Logger;
use Monolog\Handler\ExceptionLessHandler;
use League\BooBoo\Handler\LogHandler;

//Create logger
$logger = new Logger('my_logger');
$exceptionless = new ExceptionLessHandler('3V52Gdqux6gRWEWTGrthfgdBW7QU8AscOgaP','https://www.exceptionless.io', Logger::INFO);
$logger->pushHandler($exceptionless);

$booboo = new League\BooBoo\BooBoo();
$booboo->pushHandler(new LogHandler($logger));
$booboo->register();

//Now when en error is produced, booboo will send the logs to ExceptionLess service


```

# License
This tool is free software and is distributed under the MIT license. Please have a look at the LICENSE file for further information.