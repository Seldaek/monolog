### 1.22.1 (2017-03-13)

  * Fixed lots of minor issues in the new Slack integrations
  * Fixed support for allowInlineLineBreaks in LineFormatter when formatting exception backtraces

### 1.22.0 (2016-11-26)

  * Added SlackbotHandler and SlackWebhookHandler to set up Slack integration more easily
  * Added MercurialProcessor to add mercurial revision and branch names to log records
  * Added support for AWS SDK v3 in DynamoDbHandler
  * Fixed fatal errors occuring when normalizing generators that have been fully consumed
  * Fixed RollbarHandler to include a level (rollbar level), monolog_level (original name), channel and datetime (unix)
  * Fixed RollbarHandler not flushing records automatically, calling close() explicitly is not necessary anymore
  * Fixed SyslogUdpHandler to avoid sending empty frames
  * Fixed a few PHP 7.0 and 7.1 compatibility issues

### 1.21.0 (2016-07-29)

  * Break: Reverted the addition of $context when the ErrorHandler handles regular php errors from 1.20.0 as it was causing issues
  * Added support for more formats in RotatingFileHandler::setFilenameFormat as long as they have Y, m and d in order
  * Added ability to format the main line of text the SlackHandler sends by explictly setting a formatter on the handler
  * Added information about SoapFault instances in NormalizerFormatter
  * Added $handleOnlyReportedErrors option on ErrorHandler::registerErrorHandler (default true) to allow logging of all errors no matter the error_reporting level

### 1.20.0 (2016-07-02)

  * Added FingersCrossedHandler::activate() to manually trigger the handler regardless of the activation policy
  * Added StreamHandler::getUrl to retrieve the stream's URL
  * Added ability to override addRow/addTitle in HtmlFormatter
  * Added the $context to context information when the ErrorHandler handles a regular php error
  * Deprecated RotatingFileHandler::setFilenameFormat to only support 3 formats: Y, Y-m and Y-m-d
  * Fixed WhatFailureGroupHandler to work with PHP7 throwables
  * Fixed a few minor bugs

### 1.19.0 (2016-04-12)

  * Break: StreamHandler will not close streams automatically that it does not own. If you pass in a stream (not a path/url), then it will not close it for you. You can retrieve those using getStream() if needed
  * Added DeduplicationHandler to remove duplicate records from notifications across multiple requests, useful for email or other notifications on errors
  * Added ability to use `%message%` and other LineFormatter replacements in the subject line of emails sent with NativeMailHandler and SwiftMailerHandler
  * Fixed HipChatHandler handling of long messages

### 1.18.2 (2016-04-02)

  * Fixed ElasticaFormatter to use more precise dates
  * Fixed GelfMessageFormatter sending too long messages

### 1.18.1 (2016-03-13)

  * Fixed SlackHandler bug where slack dropped messages randomly
  * Fixed RedisHandler issue when using with the PHPRedis extension
  * Fixed AmqpHandler content-type being incorrectly set when using with the AMQP extension
  * Fixed BrowserConsoleHandler regression

### 1.18.0 (2016-03-01)

  * Added optional reduction of timestamp precision via `Logger->useMicrosecondTimestamps(false)`, disabling it gets you a bit of performance boost but reduces the precision to the second instead of microsecond
  * Added possibility to skip some extra stack frames in IntrospectionProcessor if you have some library wrapping Monolog that is always adding frames
  * Added `Logger->withName` to clone a logger (keeping all handlers) with a new name
  * Added FluentdFormatter for the Fluentd unix socket protocol
  * Added HandlerWrapper base class to ease the creation of handler wrappers, just extend it and override as needed
  * Added support for replacing context sub-keys using `%context.*%` in LineFormatter
  * Added support for `payload` context value in RollbarHandler
  * Added setRelease to RavenHandler to describe the application version, sent with every log
  * Added support for `fingerprint` context value in RavenHandler
  * Fixed JSON encoding errors that would gobble up the whole log record, we now handle those more gracefully by dropping chars as needed
  * Fixed write timeouts in SocketHandler and derivatives, set to 10sec by default, lower it with `setWritingTimeout()`
  * Fixed PHP7 compatibility with regard to Exception/Throwable handling in a few places

### 1.17.2 (2015-10-14)

  * Fixed ErrorHandler compatibility with non-Monolog PSR-3 loggers
  * Fixed SlackHandler handling to use slack functionalities better
  * Fixed SwiftMailerHandler bug when sending multiple emails they all had the same id
  * Fixed 5.3 compatibility regression

### 1.17.1 (2015-08-31)

  * Fixed RollbarHandler triggering PHP notices

### 1.17.0 (2015-08-30)

  * Added support for `checksum` and `release` context/extra values in RavenHandler
  * Added better support for exceptions in RollbarHandler
  * Added UidProcessor::getUid
  * Added support for showing the resource type in NormalizedFormatter
  * Fixed IntrospectionProcessor triggering PHP notices

### 1.16.0 (2015-08-09)

  * Added IFTTTHandler to notify ifttt.com triggers
  * Added Logger::setHandlers() to allow setting/replacing all handlers
  * Added $capSize in RedisHandler to cap the log size
  * Fixed StreamHandler creation of directory to only trigger when the first log write happens
  * Fixed bug in the handling of curl failures
  * Fixed duplicate logging of fatal errors when both error and fatal error handlers are registered in monolog's ErrorHandler
  * Fixed missing fatal errors records with handlers that need to be closed to flush log records
  * Fixed TagProcessor::addTags support for associative arrays

### 1.15.0 (2015-07-12)

  * Added addTags and setTags methods to change a TagProcessor
  * Added automatic creation of directories if they are missing for a StreamHandler to open a log file
  * Added retry functionality to Loggly, Cube and Mandrill handlers so they retry up to 5 times in case of network failure
  * Fixed process exit code being incorrectly reset to 0 if ErrorHandler::registerExceptionHandler was used
  * Fixed HTML/JS escaping in BrowserConsoleHandler
  * Fixed JSON encoding errors being silently suppressed (PHP 5.5+ only)

### 1.14.0 (2015-06-19)

  * Added PHPConsoleHandler to send record to Chrome's PHP Console extension and library
  * Added support for objects implementing __toString in the NormalizerFormatter
  * Added support for HipChat's v2 API in HipChatHandler
  * Added Logger::setTimezone() to initialize the timezone monolog should use in case date.timezone isn't correct for your app
  * Added an option to send formatted message instead of the raw record on PushoverHandler via ->useFormattedMessage(true)
  * Fixed curl errors being silently suppressed

### 1.13.1 (2015-03-09)

  * Fixed regression in HipChat requiring a new token to be created

### 1.13.0 (2015-03-05)

  * Added Registry::hasLogger to check for the presence of a logger instance
  * Added context.user support to RavenHandler
  * Added HipChat API v2 support in the HipChatHandler
  * Added NativeMailerHandler::addParameter to pass params to the mail() process
  * Added context data to SlackHandler when $includeContextAndExtra is true
  * Added ability to customize the Swift_Message per-email in SwiftMailerHandler
  * Fixed SwiftMailerHandler to lazily create message instances if a callback is provided
  * Fixed serialization of INF and NaN values in Normalizer and LineFormatter

### 1.12.0 (2014-12-29)

  * Break: HandlerInterface::isHandling now receives a partial record containing only a level key. This was always the intent and does not break any Monolog handler but is strictly speaking a BC break and you should check if you relied on any other field in your own handlers.
  * Added PsrHandler to forward records to another PSR-3 logger
  * Added SamplingHandler to wrap around a handler and include only every Nth record
  * Added MongoDBFormatter to support better storage with MongoDBHandler (it must be enabled manually for now)
  * Added exception codes in the output of most formatters
  * Added LineFormatter::includeStacktraces to enable exception stack traces in logs (uses more than one line)
  * Added $useShortAttachment to SlackHandler to minify attachment size and $includeExtra to append extra data
  * Added $host to HipChatHandler for users of private instances
  * Added $transactionName to NewRelicHandler and support for a transaction_name context value
  * Fixed MandrillHandler to avoid outputing API call responses
  * Fixed some non-standard behaviors in SyslogUdpHandler

### 1.11.0 (2014-09-30)

  * Break: The NewRelicHandler extra and context data are now prefixed with extra_ and context_ to avoid clashes. Watch out if you have scripts reading those from the API and rely on names
  * Added WhatFailureGroupHandler to suppress any exception coming from the wrapped handlers and avoid chain failures if a logging service fails
  * Added MandrillHandler to send emails via the Mandrillapp.com API
  * Added SlackHandler to log records to a Slack.com account
  * Added FleepHookHandler to log records to a Fleep.io account
  * Added LogglyHandler::addTag to allow adding tags to an existing handler
  * Added $ignoreEmptyContextAndExtra to LineFormatter to avoid empty [] at the end
  * Added $useLocking to StreamHandler and RotatingFileHandler to enable flock() while writing
  * Added support for PhpAmqpLib in the AmqpHandler
  * Added FingersCrossedHandler::clear and BufferHandler::clear to reset them between batches in long running jobs
  * Added support for adding extra fields from $_SERVER in the WebProcessor
  * Fixed support for non-string values in PrsLogMessageProcessor
  * Fixed SwiftMailer messages being sent with the wrong date in long running scripts
  * Fixed minor PHP 5.6 compatibility issues
  * Fixed BufferHandler::close being called twice

### 1.10.0 (2014-06-04)

  * Added Logger::getHandlers() and Logger::getProcessors() methods
  * Added $passthruLevel argument to FingersCrossedHandler to let it always pass some records through even if the trigger level is not reached
  * Added support for extra data in NewRelicHandler
  * Added $expandNewlines flag to the ErrorLogHandler to create multiple log entries when a message has multiple lines

### 1.9.1 (2014-04-24)

  * Fixed regression in RotatingFileHandler file permissions
  * Fixed initialization of the BufferHandler to make sure it gets flushed after receiving records
  * Fixed ChromePHPHandler and FirePHPHandler's activation strategies to be more conservative

### 1.9.0 (2014-04-20)

  * Added LogEntriesHandler to send logs to a LogEntries account
  * Added $filePermissions to tweak file mode on StreamHandler and RotatingFileHandler
  * Added $useFormatting flag to MemoryProcessor to make it send raw data in bytes
  * Added support for table formatting in FirePHPHandler via the table context key
  * Added a TagProcessor to add tags to records, and support for tags in RavenHandler
  * Added $appendNewline flag to the JsonFormatter to enable using it when logging to files
  * Added sound support to the PushoverHandler
  * Fixed multi-threading support in StreamHandler
  * Fixed empty headers issue when ChromePHPHandler received no records
  * Fixed default format of the ErrorLogHandler

### 1.8.0 (2014-03-23)

  * Break: the LineFormatter now strips newlines by default because this was a bug, set $allowInlineLineBreaks to true if you need them
  * Added BrowserConsoleHandler to send logs to any browser's console via console.log() injection in the output
  * Added FilterHandler to filter records and only allow those of a given list of levels through to the wrapped handler
  * Added FlowdockHandler to send logs to a Flowdock account
  * Added RollbarHandler to send logs to a Rollbar account
  * Added HtmlFormatter to send prettier log emails with colors for each log level
  * Added GitProcessor to add the current branch/commit to extra record data
  * Added a Monolog\Registry class to allow easier global access to pre-configured loggers
  * Added support for the new official graylog2/gelf-php lib for GelfHandler, upgrade if you can by replacing the mlehner/gelf-php requirement
  * Added support for HHVM
  * Added support for Loggly batch uploads
  * Added support for tweaking the content type and encoding in NativeMailerHandler
  * Added $skipClassesPartials to tweak the ignored classes in the IntrospectionProcessor
  * Fixed batch request support in GelfHandler

### 1.7.0 (2013-11-14)

  * Added ElasticSearchHandler to send logs to an Elastic Search server
  * Added DynamoDbHandler and ScalarFormatter to send logs to Amazon's Dynamo DB
  * Added SyslogUdpHandler to send logs to a remote syslogd server
  * Added LogglyHandler to send logs to a Loggly account
  * Added $level to IntrospectionProcessor so it only adds backtraces when needed
  * Added $version to LogstashFormatter to allow using the new v1 Logstash format
  * Added $appName to NewRelicHandler
  * Added configuration of Pushover notification retries/expiry
  * Added $maxColumnWidth to NativeMailerHandler to change the 70 chars default
  * Added chainability to most setters for all handlers
  * Fixed RavenHandler batch processing so it takes the message from the record with highest priority
  * Fixed HipChatHandler batch processing so it sends all messages at once
  * Fixed issues with eAccelerator
  * Fixed and improved many small things

### 1.6.0 (2013-07-29)

  * Added HipChatHandler to send logs to a HipChat chat room
  * Added ErrorLogHandler to send logs to PHP's error_log function
  * Added NewRelicHandler to send logs to NewRelic's service
  * Added Monolog\ErrorHandler helper class to register a Logger as exception/error/fatal handler
  * Added ChannelLevelActivationStrategy for the FingersCrossedHandler to customize levels by channel
  * Added stack traces output when normalizing exceptions (json output & co)
  * Added Monolog\Logger::API constant (currently 1)
  * Added support for ChromePHP's v4.0 extension
  * Added support for message priorities in PushoverHandler, see $highPriorityLevel and $emergencyLevel
  * Added support for sending messages to multiple users at once with the PushoverHandler
  * Fixed RavenHandler's support for batch sending of messages (when behind a Buffer or FingersCrossedHandler)
  * Fixed normalization of Traversables with very large data sets, only the first 1000 items are shown now
  * Fixed issue in RotatingFileHandler when an open_basedir restriction is active
  * Fixed minor issues in RavenHandler and bumped the API to Raven 0.5.0
  * Fixed SyslogHandler issue when many were used concurrently with different facilities

### 1.5.0 (2013-04-23)

  * Added ProcessIdProcessor to inject the PID in log records
  * Added UidProcessor to inject a unique identifier to all log records of one request/run
  * Added support for previous exceptions in the LineFormatter exception serialization
  * Added Monolog\Logger::getLevels() to get all available levels
  * Fixed ChromePHPHandler so it avoids sending headers larger than Chrome can handle

### 1.4.1 (2013-04-01)

  * Fixed exception formatting in the LineFormatter to be more minimalistic
  * Fixed RavenHandler's handling of context/extra data, requires Raven client >0.1.0
  * Fixed log rotation in RotatingFileHandler to work with long running scripts spanning multiple days
  * Fixed WebProcessor array access so it checks for data presence
  * Fixed Buffer, Group and FingersCrossed handlers to make use of their processors

### 1.4.0 (2013-02-13)

  * Added RedisHandler to log to Redis via the Predis library or the phpredis extension
  * Added ZendMonitorHandler to log to the Zend Server monitor
  * Added the possibility to pass arrays of handlers and processors directly in the Logger constructor
  * Added `$useSSL` option to the PushoverHandler which is enabled by default
  * Fixed ChromePHPHandler and FirePHPHandler issue when multiple instances are used simultaneously
  * Fixed header injection capability in the NativeMailHandler

### 1.3.1 (2013-01-11)

  * Fixed LogstashFormatter to be usable with stream handlers
  * Fixed GelfMessageFormatter levels on Windows

### 1.3.0 (2013-01-08)

  * Added PSR-3 compliance, the `Monolog\Logger` class is now an instance of `Psr\Log\LoggerInterface`
  * Added PsrLogMessageProcessor that you can selectively enable for full PSR-3 compliance
  * Added LogstashFormatter (combine with SocketHandler or StreamHandler to send logs to Logstash)
  * Added PushoverHandler to send mobile notifications
  * Added CouchDBHandler and DoctrineCouchDBHandler
  * Added RavenHandler to send data to Sentry servers
  * Added support for the new MongoClient class in MongoDBHandler
  * Added microsecond precision to log records' timestamps
  * Added `$flushOnOverflow` param to BufferHandler to flush by batches instead of losing
    the oldest entries
  * Fixed normalization of objects with cyclic references

### 1.2.1 (2012-08-29)

  * Added new $logopts arg to SyslogHandler to provide custom openlog options
  * Fixed fatal error in SyslogHandler

### 1.2.0 (2012-08-18)

  * Added AmqpHandler (for use with AMQP servers)
  * Added CubeHandler
  * Added NativeMailerHandler::addHeader() to send custom headers in mails
  * Added the possibility to specify more than one recipient in NativeMailerHandler
  * Added the possibility to specify float timeouts in SocketHandler
  * Added NOTICE and EMERGENCY levels to conform with RFC 5424
  * Fixed the log records to use the php default timezone instead of UTC
  * Fixed BufferHandler not being flushed properly on PHP fatal errors
  * Fixed normalization of exotic resource types
  * Fixed the default format of the SyslogHandler to avoid duplicating datetimes in syslog

### 1.1.0 (2012-04-23)

  * Added Monolog\Logger::isHandling() to check if a handler will
    handle the given log level
  * Added ChromePHPHandler
  * Added MongoDBHandler
  * Added GelfHandler (for use with Graylog2 servers)
  * Added SocketHandler (for use with syslog-ng for example)
  * Added NormalizerFormatter
  * Added the possibility to change the activation strategy of the FingersCrossedHandler
  * Added possibility to show microseconds in logs
  * Added `server` and `referer` to WebProcessor output

### 1.0.2 (2011-10-24)

  * Fixed bug in IE with large response headers and FirePHPHandler

### 1.0.1 (2011-08-25)

  * Added MemoryPeakUsageProcessor and MemoryUsageProcessor
  * Added Monolog\Logger::getName() to get a logger's channel name

### 1.0.0 (2011-07-06)

  * Added IntrospectionProcessor to get info from where the logger was called
  * Fixed WebProcessor in CLI

### 1.0.0-RC1 (2011-07-01)

  * Initial release
