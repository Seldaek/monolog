# Handlers, Formatters and Processors

- [Handlers](#handlers)
  - [Log to files and syslog](#log-to-files-and-syslog)
  - [Send alerts and emails](#send-alerts-and-emails)
  - [Log specific servers and networked logging](#log-specific-servers-and-networked-logging)
  - [Logging in development](#logging-in-development)
  - [Log to databases](#log-to-databases)
  - [Wrappers / Special Handlers](#wrappers--special-handlers)
- [Formatters](#formatters)
- [Processors](#processors)
- [Third Party Packages](#third-party-packages)

## Handlers

### Log to files and syslog

- [_StreamHandler_](../src/Monolog/Handler/StreamHandler.php): Logs records into any PHP stream, use this for log files.
- [_RotatingFileHandler_](../src/Monolog/Handler/RotatingFileHandler.php): Logs records to a file and creates one logfile per day.
  It will also delete files older than `$maxFiles`. You should use
  [logrotate](http://linuxcommand.org/man_pages/logrotate8.html) for high profile
  setups though, this is just meant as a quick and dirty solution.
- [_SyslogHandler_](../src/Monolog/Handler/SyslogHandler.php): Logs records to the syslog.
- [_ErrorLogHandler_](../src/Monolog/Handler/ErrorLogHandler.php): Logs records to PHP's
  [`error_log()`](http://docs.php.net/manual/en/function.error-log.php) function.
- [_ProcessHandler_](../src/Monolog/Handler/ProcessHandler.php): Logs records to the [STDIN](https://en.wikipedia.org/wiki/Standard_streams#Standard_input_.28stdin.29) of any process, specified by a command.

### Send alerts and emails

- [_NativeMailerHandler_](../src/Monolog/Handler/NativeMailerHandler.php): Sends emails using PHP's
  [`mail()`](http://php.net/manual/en/function.mail.php) function.
- [_SwiftMailerHandler_](../src/Monolog/Handler/SwiftMailerHandler.php): Sends emails using a [`Swift_Mailer`](http://swiftmailer.org/) instance.
- [_PushoverHandler_](../src/Monolog/Handler/PushoverHandler.php): Sends mobile notifications via the [Pushover](https://www.pushover.net/) API.
- [_HipChatHandler_](../src/Monolog/Handler/HipChatHandler.php): Logs records to a [HipChat](http://hipchat.com) chat room using its API.
- [_FlowdockHandler_](../src/Monolog/Handler/FlowdockHandler.php): Logs records to a [Flowdock](https://www.flowdock.com/) account.
- [_SlackbotHandler_](../src/Monolog/Handler/SlackbotHandler.php): Logs records to a [Slack](https://www.slack.com/) account using the Slackbot incoming hook.
- [_SlackWebhookHandler_](../src/Monolog/Handler/SlackWebhookHandler.php): Logs records to a [Slack](https://www.slack.com/) account using Slack Webhooks.
- [_SlackHandler_](../src/Monolog/Handler/SlackHandler.php): Logs records to a [Slack](https://www.slack.com/) account using the Slack API (complex setup).
- [_SendGridHandler_](../src/Monolog/Handler/SendGridHandler.php): Sends emails via the SendGrid API.
- [_MandrillHandler_](../src/Monolog/Handler/MandrillHandler.php): Sends emails via the Mandrill API using a [`Swift_Message`](http://swiftmailer.org/) instance.
- [_FleepHookHandler_](../src/Monolog/Handler/FleepHookHandler.php): Logs records to a [Fleep](https://fleep.io/) conversation using Webhooks.
- [_IFTTTHandler_](../src/Monolog/Handler/IFTTTHandler.php): Notifies an [IFTTT](https://ifttt.com/maker) trigger with the log channel, level name and message.

### Log specific servers and networked logging

- [_SocketHandler_](../src/Monolog/Handler/SocketHandler.php): Logs records to [sockets](http://php.net/fsockopen), use this
  for UNIX and TCP sockets. See an [example](sockets.md).
- [_AmqpHandler_](../src/Monolog/Handler/AmqpHandler.php): Logs records to an [amqp](http://www.amqp.org/) compatible
  server. Requires the [php-amqp](http://pecl.php.net/package/amqp) extension (1.0+).
- [_GelfHandler_](../src/Monolog/Handler/GelfHandler.php): Logs records to a [Graylog2](http://www.graylog2.org) server.
- [_CubeHandler_](../src/Monolog/Handler/CubeHandler.php): Logs records to a [Cube](http://square.github.com/cube/) server.
- [_RavenHandler_](../src/Monolog/Handler/RavenHandler.php): Logs records to a [Sentry](http://getsentry.com/) server using
  [raven](https://packagist.org/packages/raven/raven).
- [_ZendMonitorHandler_](../src/Monolog/Handler/ZendMonitorHandler.php): Logs records to the Zend Monitor present in Zend Server.
- [_NewRelicHandler_](../src/Monolog/Handler/NewRelicHandler.php): Logs records to a [NewRelic](http://newrelic.com/) application.
- [_LogglyHandler_](../src/Monolog/Handler/LogglyHandler.php): Logs records to a [Loggly](http://www.loggly.com/) account.
- [_RollbarHandler_](../src/Monolog/Handler/RollbarHandler.php): Logs records to a [Rollbar](https://rollbar.com/) account.
- [_SyslogUdpHandler_](../src/Monolog/Handler/SyslogUdpHandler.php): Logs records to a remote [Syslogd](http://www.rsyslog.com/) server.
- [_LogEntriesHandler_](../src/Monolog/Handler/LogEntriesHandler.php): Logs records to a [LogEntries](http://logentries.com/) account.
- [_LogmaticHandler_](../src/Monolog/Handler/LogmaticHandler.php): Logs records to a [Logmatic](http://logmatic.io/) account.
- [_SqsHandler_](../src/Monolog/Handler/SqsHandler.php): Logs records to an [AWS SQS](http://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-sqs.html) queue.

### Logging in development

- [_FirePHPHandler_](../src/Monolog/Handler/FirePHPHandler.php): Handler for [FirePHP](http://www.firephp.org/), providing
  inline `console` messages within [FireBug](http://getfirebug.com/).
- [_ChromePHPHandler_](../src/Monolog/Handler/ChromePHPHandler.php): Handler for [ChromePHP](http://www.chromephp.com/), providing
  inline `console` messages within Chrome.
- [_BrowserConsoleHandler_](../src/Monolog/Handler/BrowserConsoleHandler.php): Handler to send logs to browser's Javascript `console` with
  no browser extension required. Most browsers supporting `console` API are supported.
- [_PHPConsoleHandler_](../src/Monolog/Handler/PHPConsoleHandler.php): Handler for [PHP Console](https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef), providing
  inline `console` and notification popup messages within Chrome.

### Log to databases

- [_RedisHandler_](../src/Monolog/Handler/RedisHandler.php): Logs records to a [redis](http://redis.io) server.
- [_MongoDBHandler_](../src/Monolog/Handler/MongoDBHandler.php): Handler to write records in MongoDB via a
  [Mongo](http://pecl.php.net/package/mongo) extension connection.
- [_CouchDBHandler_](../src/Monolog/Handler/CouchDBHandler.php): Logs records to a CouchDB server.
- [_DoctrineCouchDBHandler_](../src/Monolog/Handler/DoctrineCouchDBHandler.php): Logs records to a CouchDB server via the Doctrine CouchDB ODM.
- [_ElasticSearchHandler_](../src/Monolog/Handler/ElasticSearchHandler.php): Logs records to an Elastic Search server.
- [_DynamoDbHandler_](../src/Monolog/Handler/DynamoDbHandler.php): Logs records to a DynamoDB table with the [AWS SDK](https://github.com/aws/aws-sdk-php).

### Wrappers / Special Handlers

- [_FingersCrossedHandler_](../src/Monolog/Handler/FingersCrossedHandler.php): A very interesting wrapper. It takes a logger as
  parameter and will accumulate log records of all levels until a record
  exceeds the defined severity level. At which point it delivers all records,
  including those of lower severity, to the handler it wraps. This means that
  until an error actually happens you will not see anything in your logs, but
  when it happens you will have the full information, including debug and info
  records. This provides you with all the information you need, but only when
  you need it.
- [_DeduplicationHandler_](../src/Monolog/Handler/DeduplicationHandler.php): Useful if you are sending notifications or emails
  when critical errors occur. It takes a logger as parameter and will
  accumulate log records of all levels until the end of the request (or
  `flush()` is called). At that point it delivers all records to the handler
  it wraps, but only if the records are unique over a given time period
  (60seconds by default). If the records are duplicates they are simply
  discarded. The main use of this is in case of critical failure like if your
  database is unreachable for example all your requests will fail and that
  can result in a lot of notifications being sent. Adding this handler reduces
  the amount of notifications to a manageable level.
- [_WhatFailureGroupHandler_](../src/Monolog/Handler/WhatFailureGroupHandler.php): This handler extends the _GroupHandler_ ignoring
   exceptions raised by each child handler. This allows you to ignore issues
   where a remote tcp connection may have died but you do not want your entire
   application to crash and may wish to continue to log to other handlers.
- [_BufferHandler_](../src/Monolog/Handler/BufferHandler.php): This handler will buffer all the log records it receives
  until `close()` is called at which point it will call `handleBatch()` on the
  handler it wraps with all the log messages at once. This is very useful to
  send an email with all records at once for example instead of having one mail
  for every log record.
- [_GroupHandler_](../src/Monolog/Handler/GroupHandler.php): This handler groups other handlers. Every record received is
  sent to all the handlers it is configured with.
- [_FilterHandler_](../src/Monolog/Handler/FilterHandler.php): This handler only lets records of the given levels through
   to the wrapped handler.
- [_SamplingHandler_](../src/Monolog/Handler/SamplingHandler.php): Wraps around another handler and lets you sample records
   if you only want to store some of them.
- [_NoopHandler_](../src/Monolog/Handler/NoopHandler.php): This handler handles anything by doing nothing. It does not stop
  processing the rest of the stack. This can be used for testing, or to disable a handler when overriding a configuration.
- [_NullHandler_](../src/Monolog/Handler/NullHandler.php): Any record it can handle will be thrown away. This can be used
  to put on top of an existing handler stack to disable it temporarily.
- [_PsrHandler_](../src/Monolog/Handler/PsrHandler.php): Can be used to forward log records to an existing PSR-3 logger
- [_TestHandler_](../src/Monolog/Handler/TestHandler.php): Used for testing, it records everything that is sent to it and
  has accessors to read out the information.
- [_HandlerWrapper_](../src/Monolog/Handler/HandlerWrapper.php): A simple handler wrapper you can inherit from to create
 your own wrappers easily.

## Formatters

- [_LineFormatter_](../src/Monolog/Formatter/LineFormatter.php): Formats a log record into a one-line string.
- [_HtmlFormatter_](../src/Monolog/Formatter/HtmlFormatter.php): Used to format log records into a human readable html table, mainly suitable for emails.
- [_NormalizerFormatter_](../src/Monolog/Formatter/NormalizerFormatter.php): Normalizes objects/resources down to strings so a record can easily be serialized/encoded.
- [_ScalarFormatter_](../src/Monolog/Formatter/ScalarFormatter.php): Used to format log records into an associative array of scalar values.
- [_JsonFormatter_](../src/Monolog/Formatter/JsonFormatter.php): Encodes a log record into json.
- [_WildfireFormatter_](../src/Monolog/Formatter/WildfireFormatter.php): Used to format log records into the Wildfire/FirePHP protocol, only useful for the FirePHPHandler.
- [_ChromePHPFormatter_](../src/Monolog/Formatter/ChromePHPFormatter.php): Used to format log records into the ChromePHP format, only useful for the ChromePHPHandler.
- [_GelfMessageFormatter_](../src/Monolog/Formatter/GelfMessageFormatter.php): Used to format log records into Gelf message instances, only useful for the GelfHandler.
- [_LogstashFormatter_](../src/Monolog/Formatter/LogstashFormatter.php): Used to format log records into [logstash](http://logstash.net/) event json, useful for any handler listed under inputs [here](http://logstash.net/docs/latest).
- [_ElasticaFormatter_](../src/Monolog/Formatter/ElasticaFormatter.php): Used to format log records into an Elastica\Document object, only useful for the ElasticSearchHandler.
- [_LogglyFormatter_](../src/Monolog/Formatter/LogglyFormatter.php): Used to format log records into Loggly messages, only useful for the LogglyHandler.
- [_FlowdockFormatter_](../src/Monolog/Formatter/FlowdockFormatter.php): Used to format log records into Flowdock messages, only useful for the FlowdockHandler.
- [_MongoDBFormatter_](../src/Monolog/Formatter/MongoDBFormatter.php): Converts \DateTime instances to \MongoDate and objects recursively to arrays, only useful with the MongoDBHandler.
- [_LogmaticFormatter_](../src/Monolog/Formatter/LogmaticFormatter.php): User to format log records to [Logmatic](http://logmatic.io/) messages, only useful for the LogmaticHandler.

## Processors

- [_PsrLogMessageProcessor_](../src/Monolog/Processor/PsrLogMessageProcessor.php): Processes a log record's message according to PSR-3 rules, replacing `{foo}` with the value from `$context['foo']`.
- [_IntrospectionProcessor_](../src/Monolog/Processor/IntrospectionProcessor.php): Adds the line/file/class/method from which the log call originated.
- [_WebProcessor_](../src/Monolog/Processor/WebProcessor.php): Adds the current request URI, request method and client IP to a log record.
- [_MemoryUsageProcessor_](../src/Monolog/Processor/MemoryUsageProcessor.php): Adds the current memory usage to a log record.
- [_MemoryPeakUsageProcessor_](../src/Monolog/Processor/MemoryPeakUsageProcessor.php): Adds the peak memory usage to a log record.
- [_ProcessIdProcessor_](../src/Monolog/Processor/ProcessIdProcessor.php): Adds the process id to a log record.
- [_UidProcessor_](../src/Monolog/Processor/UidProcessor.php): Adds a unique identifier to a log record.
- [_GitProcessor_](../src/Monolog/Processor/GitProcessor.php): Adds the current git branch and commit to a log record.
- [_MercurialProcessor_](../src/Monolog/Processor/MercurialProcessor.php): Adds the current hg branch and commit to a log record.
- [_TagProcessor_](../src/Monolog/Processor/TagProcessor.php): Adds an array of predefined tags to a log record.

## Third Party Packages

Third party handlers, formatters and processors are
[listed in the wiki](https://github.com/Seldaek/monolog/wiki/Third-Party-Packages). You
can also add your own there if you publish one.

&larr; [Usage](01-usage.md) |  [Utility classes](03-utilities.md) &rarr;
