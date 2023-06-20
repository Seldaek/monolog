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

- [_StreamHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/StreamHandler.php): Logs records into any PHP stream, use this for log files.
- [_RotatingFileHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/RotatingFileHandler.php): Logs records to a file and creates one log file per day.
  It will also delete files older than `$maxFiles`. You should use
  [logrotate](https://linux.die.net/man/8/logrotate) for high profile
  setups though, this is just meant as a quick and dirty solution.
- [_SyslogHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SyslogHandler.php): Logs records to the syslog.
- [_ErrorLogHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/ErrorLogHandler.php): Logs records to PHP's
  [`error_log()`](http://docs.php.net/manual/en/function.error-log.php) function.
- [_ProcessHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/ProcessHandler.php): Logs records to the [STDIN](https://en.wikipedia.org/wiki/Standard_streams#Standard_input_.28stdin.29) of any process, specified by a command.

### Send alerts and emails

- [_NativeMailerHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/NativeMailerHandler.php): Sends emails using PHP's
  [`mail()`](http://php.net/manual/en/function.mail.php) function.
- [_SymfonyMailerHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SymfonyMailerHandler.php): Sends emails using a [`symfony/mailer`](https://symfony.com/doc/current/mailer.html) instance.
- [_PushoverHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/PushoverHandler.php): Sends mobile notifications via the [Pushover](https://www.pushover.net/) API.
- [_SlackWebhookHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SlackWebhookHandler.php): Logs records to a [Slack](https://www.slack.com/) account using Slack Webhooks.
- [_SlackHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SlackHandler.php): Logs records to a [Slack](https://www.slack.com/) account using the Slack API (complex setup).
- [_SendGridHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SendGridHandler.php): Sends emails via the SendGrid API.
- [_MandrillHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/MandrillHandler.php): Sends emails via the [`Mandrill API`](https://mandrillapp.com/api/docs/) using a [`Swift_Message`](http://swiftmailer.org/) instance.
- [_FleepHookHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/FleepHookHandler.php): Logs records to a [Fleep](https://fleep.io/) conversation using Webhooks.
- [_IFTTTHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/IFTTTHandler.php): Notifies an [IFTTT](https://ifttt.com/maker) trigger with the log channel, level name and message.
- [_TelegramBotHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/TelegramBotHandler.php): Logs records to a [Telegram](https://core.telegram.org/bots/api) bot account.
- [_HipChatHandler_](https://github.com/Seldaek/monolog/blob/1.x/src/Monolog/Handler/HipChatHandler.php): Logs records to a [HipChat](http://hipchat.com) chat room using its API. **Deprecated** and removed in Monolog 2.0, use Slack handlers instead, see [Atlassian's announcement](https://www.atlassian.com/partnerships/slack)
- [_SwiftMailerHandler_](https://github.com/Seldaek/monolog/blob/2.x/src/Monolog/Handler/SwiftMailerHandler.php): Sends emails using a [`Swift_Mailer`](http://swiftmailer.org/) instance. **Deprecated** and removed in Monolog 3.0. Use SymfonyMailerHandler instead.

### Log specific servers and networked logging

- [_SocketHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SocketHandler.php): Logs records to [sockets](http://php.net/fsockopen), use this
  for UNIX and TCP sockets. See an [example](sockets.md).
- [_AmqpHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/AmqpHandler.php): Logs records to an [AMQP](http://www.amqp.org/) compatible
  server. Requires the [php-amqp](http://pecl.php.net/package/amqp) extension (1.0+) or
  [php-amqplib](https://github.com/php-amqplib/php-amqplib) library.
- [_GelfHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/GelfHandler.php): Logs records to a [Graylog2](http://www.graylog2.org) server.
  Requires package [graylog2/gelf-php](https://github.com/bzikarsky/gelf-php).
- [_ZendMonitorHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/ZendMonitorHandler.php): Logs records to the Zend Monitor present in Zend Server.
- [_NewRelicHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/NewRelicHandler.php): Logs records to a [NewRelic](http://newrelic.com/) application.
- [_LogglyHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/LogglyHandler.php): Logs records to a [Loggly](http://www.loggly.com/) account.
- [_RollbarHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/RollbarHandler.php): Logs records to a [Rollbar](https://rollbar.com/) account.
- [_SyslogUdpHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SyslogUdpHandler.php): Logs records to a remote [Syslogd](http://www.rsyslog.com/) server.
- [_LogEntriesHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/LogEntriesHandler.php): Logs records to a [LogEntries](http://logentries.com/) account.
- [_InsightOpsHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/InsightOpsHandler.php): Logs records to an [InsightOps](https://www.rapid7.com/products/insightops/) account.
- [_LogmaticHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/LogmaticHandler.php): Logs records to a [Logmatic](http://logmatic.io/) account.
- [_SqsHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SqsHandler.php): Logs records to an [AWS SQS](http://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-sqs.html) queue.
- [_RavenHandler_](https://github.com/Seldaek/monolog/blob/1.x/src/Monolog/Handler/RavenHandler.php): Logs records to a [Sentry](http://getsentry.com/) server using
  [raven](https://packagist.org/packages/raven/raven). **Deprecated** and removed in Monolog 2.0, use sentry/sentry 2.x and the [Sentry\Monolog\Handler](https://github.com/getsentry/sentry-php/blob/master/src/Monolog/Handler.php) class instead.

### Logging in development

- [_FirePHPHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/FirePHPHandler.php): Handler for [FirePHP](http://www.firephp.org/), providing
  inline `console` messages within [FireBug](http://getfirebug.com/).
- [_ChromePHPHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/ChromePHPHandler.php): Handler for [ChromePHP](http://www.chromephp.com/), providing
  inline `console` messages within Chrome.
- [_BrowserConsoleHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/BrowserConsoleHandler.php): Handler to send logs to browser's Javascript `console` with
  no browser extension required. Most browsers supporting `console` API are supported.

### Log to databases

- [_RedisHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/RedisHandler.php): Logs records to a [redis](http://redis.io) server's key via RPUSH.
- [_RedisPubSubHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/RedisPubSubHandler.php): Logs records to a [redis](http://redis.io) server's channel via PUBLISH.
- [_MongoDBHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/MongoDBHandler.php): Handler to write records in MongoDB via a
  [Mongo](http://pecl.php.net/package/mongo) extension connection.
- [_CouchDBHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/CouchDBHandler.php): Logs records to a CouchDB server.
- [_DoctrineCouchDBHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/DoctrineCouchDBHandler.php): Logs records to a CouchDB server via the Doctrine CouchDB ODM.
- [_ElasticaHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/ElasticaHandler.php): Logs records to an Elasticsearch server using [ruflin/elastica](https://elastica.io/).
- [_ElasticsearchHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/ElasticsearchHandler.php): Logs records to an Elasticsearch server.
- [_DynamoDbHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/DynamoDbHandler.php): Logs records to a DynamoDB table with the [AWS SDK](https://github.com/aws/aws-sdk-php).

### Wrappers / Special Handlers

- [_FingersCrossedHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/FingersCrossedHandler.php): A very interesting wrapper. It takes a handler as
  a parameter and will accumulate log records of all levels until a record
  exceeds the defined severity level. At which point it delivers all records,
  including those of lower severity, to the handler it wraps. This means that
  until an error actually happens you will not see anything in your logs, but
  when it happens you will have the full information, including debug and info
  records. This provides you with all the information you need, but only when
  you need it.
- [_DeduplicationHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/DeduplicationHandler.php): Useful if you are sending notifications or emails
  when critical errors occur. It takes a handler as a parameter and will
  accumulate log records of all levels until the end of the request (or
  `flush()` is called). At that point it delivers all records to the handler
  it wraps, but only if the records are unique over a given time period
  (60seconds by default). If the records are duplicates they are simply
  discarded. The main use of this is in case of critical failure like if your
  database is unreachable for example all your requests will fail and that
  can result in a lot of notifications being sent. Adding this handler reduces
  the amount of notifications to a manageable level.
- [_WhatFailureGroupHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/WhatFailureGroupHandler.php): This handler extends the _GroupHandler_ ignoring
   exceptions raised by each child handler. This allows you to ignore issues
   where a remote tcp connection may have died but you do not want your entire
   application to crash and may wish to continue to log to other handlers.
- [_FallbackGroupHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/FallbackGroupHandler.php): This handler extends the _GroupHandler_ ignoring
  exceptions raised by each child handler, until one has handled without throwing.
  This allows you to ignore issues where a remote tcp connection may have died
  but you do not want your entire application to crash and may wish to continue
  to attempt logging to other handlers, until one does not throw an exception.
- [_BufferHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/BufferHandler.php): This handler will buffer all the log records it receives
  until `close()` is called at which point it will call `handleBatch()` on the
  handler it wraps with all the log messages at once. This is very useful to
  send an email with all records at once for example instead of having one mail
  for every log record.
- [_GroupHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/GroupHandler.php): This handler groups other handlers. Every record received is
  sent to all the handlers it is configured with.
- [_FilterHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/FilterHandler.php): This handler only lets records of the given levels through
   to the wrapped handler.
- [_SamplingHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/SamplingHandler.php): Wraps around another handler and lets you sample records
   if you only want to store some of them.
- [_NoopHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/NoopHandler.php): This handler handles anything by doing nothing. It does not stop
  processing the rest of the stack. This can be used for testing, or to disable a handler when overriding a configuration.
- [_NullHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/NullHandler.php): Any record it can handle will be thrown away. This can be used
  to put on top of an existing handler stack to disable it temporarily.
- [_PsrHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/PsrHandler.php): Can be used to forward log records to an existing PSR-3 logger
- [_TestHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/TestHandler.php): Used for testing, it records everything that is sent to it and
  has accessors to read out the information.
- [_HandlerWrapper_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/HandlerWrapper.php): A simple handler wrapper you can inherit from to create
 your own wrappers easily.
- [_OverflowHandler_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Handler/OverflowHandler.php): This handler will buffer all the log messages it
  receives, up until a configured threshold of number of messages of a certain level is reached, after it will pass all
  log messages to the wrapped handler. Useful for applying in batch processing when you're only interested in significant
  failures instead of minor, single erroneous events.

## Formatters

- [_LineFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/LineFormatter.php): Formats a log record into a one-line string.
- [_HtmlFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/HtmlFormatter.php): Used to format log records into a human readable html table, mainly suitable for emails.
- [_NormalizerFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/NormalizerFormatter.php): Normalizes objects/resources down to strings so a record can easily be serialized/encoded.
- [_ScalarFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/ScalarFormatter.php): Used to format log records into an associative array of scalar values.
- [_JsonFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/JsonFormatter.php): Encodes a log record into json.
- [_WildfireFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/WildfireFormatter.php): Used to format log records into the Wildfire/FirePHP protocol, only useful for the FirePHPHandler.
- [_ChromePHPFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/ChromePHPFormatter.php): Used to format log records into the ChromePHP format, only useful for the ChromePHPHandler.
- [_GelfMessageFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/GelfMessageFormatter.php): Used to format log records into Gelf message instances, only useful for the GelfHandler.
- [_LogstashFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/LogstashFormatter.php): Used to format log records into [logstash](http://logstash.net/) event json, useful for any handler listed under inputs [here](http://logstash.net/docs/latest).
- [_ElasticaFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/ElasticaFormatter.php): Used to format log records into an Elastica\Document object, only useful for the ElasticaHandler.
- [_ElasticsearchFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/ElasticsearchFormatter.php): Used to add index and type keys to log records, only useful for the ElasticsearchHandler.
- [_LogglyFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/LogglyFormatter.php): Used to format log records into Loggly messages, only useful for the LogglyHandler.
- [_MongoDBFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/MongoDBFormatter.php): Converts \DateTime instances to \MongoDate and objects recursively to arrays, only useful with the MongoDBHandler.
- [_LogmaticFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/LogmaticFormatter.php): Used to format log records to [Logmatic](http://logmatic.io/) messages, only useful for the LogmaticHandler.
- [_FluentdFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/FluentdFormatter.php): Used to format log records to [Fluentd](https://www.fluentd.org/) logs, only useful with the SocketHandler.
- [_GoogleCloudLoggingFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/GoogleCloudLoggingFormatter.php): Used to format log records for Google Cloud Logging. It works like a JsonFormatter with some minor tweaks.
- [_SyslogFormatter_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/SyslogFormatter.php): Used to format log records in RFC 5424 / syslog format. This can be used to output a syslog-style file that can then be consumed by tools like [lnav](https://lnav.org/).

## Processors

- [_PsrLogMessageProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/PsrLogMessageProcessor.php): Processes a log record's message according to PSR-3 rules, replacing `{foo}` with the value from `$context['foo']`.
- [_LoadAverageProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/LoadAverageProcessor.php): Adds the current system load average to a log record.
- [_ClosureContextProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/ClosureContextProcessor.php): Allows delaying the creation of context data by setting a Closure in context which is called when the log record is used
- [_IntrospectionProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/IntrospectionProcessor.php): Adds the line/file/class/method from which the log call originated.
- [_WebProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/WebProcessor.php): Adds the current request URI, request method and client IP to a log record.
- [_MemoryUsageProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/MemoryUsageProcessor.php): Adds the current memory usage to a log record.
- [_MemoryPeakUsageProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/MemoryPeakUsageProcessor.php): Adds the peak memory usage to a log record.
- [_ProcessIdProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/ProcessIdProcessor.php): Adds the process id to a log record.
- [_UidProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/UidProcessor.php): Adds a unique identifier to a log record.
- [_GitProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/GitProcessor.php): Adds the current git branch and commit to a log record.
- [_MercurialProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/MercurialProcessor.php): Adds the current hg branch and commit to a log record.
- [_TagProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/TagProcessor.php): Adds an array of predefined tags to a log record.
- [_HostnameProcessor_](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Processor/HostnameProcessor.php): Adds the current hostname to a log record.

## Third Party Packages

Third party handlers, formatters and processors are
[listed in the wiki](https://github.com/Seldaek/monolog/wiki/Third-Party-Packages). You
can also add your own there if you publish one.

&larr; [Usage](01-usage.md) |  [Utility classes](03-utilities.md) &rarr;
