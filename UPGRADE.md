### 3.0.0

Overall / notable changes:

- The minimum supported PHP version is now `8.1.0`.
- `Monolog\Logger::API` can be used to distinguish between a Monolog `3`, `2` or `1`
  install when writing integration code.
- Log records have been converted from an array to a [`Monolog\LogRecord` object](src/Monolog/LogRecord.php)
  with public (and mostly readonly) properties. e.g. instead of doing
  `$record['context']` use `$record->context`.
  In formatters or handlers if you rather need an array to work with you can use `$record->toArray()`
  to get back a Monolog 1/2 style record array. This will contain the enum values instead of enum cases
  in the `level` and `level_name` keys to be more backwards compatible and use simpler data types.
- `FormatterInterface`, `HandlerInterface`, `ProcessorInterface`, etc. changed to contain `LogRecord $record`
  instead of `array $record` parameter types. If you want to support multiple Monolog versions this should
  be possible by type-hinting nothing, or `array|LogRecord` if you support PHP 8.0+. You can then code
  against the $record using Monolog 2 style as LogRecord implements ArrayAccess for BC.
  The interfaces do not require a `LogRecord` return type even where it would be applicable, but if you only
  support Monolog 3 in integration code I would recommend you use `LogRecord` return types wherever fitting
  to ensure forward compatibility as it may be added in Monolog 4.
- Log levels are now stored as an enum [`Monolog\Level`](src/Monolog/Level.php)
- All properties have had types added, which may require you to do so as well if you extended
  a Monolog class and declared the same property.

#### Logger

- `Logger::DEBUG`, `Logger::ERROR`, etc. are now deprecated in favor of the `Level` enum.
  e.g. instead of `Logger::WARNING` use `Level::Warning` if you need to pass the enum case
  to Monolog or one of its handlers, or `Level::Warning->value` if you need the integer
  value equal to what `Logger::WARNING` was giving you.
- `Logger::$levels` has been removed.
- `Logger::getLevels` has been removed in favor of `Monolog\Level::VALUES` or `Monolog\Level::cases()`.
- `setExceptionHandler` now requires a `Closure` instance and not just any `callable`.

#### HtmlFormatter

- If you redefined colors in the `$logLevels` property you must now override the
  `getLevelColor` method instead.

#### NormalizerFormatter

- A new `normalizeRecord` method is available as an extension point which is called
  only when converting the LogRecord to an array. You may need this if you overrode
  `format` previously as `parent::format` now needs to receive a LogRecord still
  so you cannot modify it before.

#### AbstractSyslogHandler

- If you redefined syslog levels in the `$logLevels` property you must now override the
  `toSyslogPriority` method instead.

#### DynamoDbHandler

- Dropped support for AWS SDK v2

#### FilterHandler

- The factory callable to lazy load the nested handler must now be a `Closure` instance
  and not just a `callable`.

#### FingersCrossedHandler

- The factory callable to lazy load the nested handler must now be a `Closure` instance
  and not just a `callable`.

#### GelfHandler

- Dropped support for Gelf <1.1 and added support for graylog2/gelf-php v2.x. File, level
  and facility are now passed in as additional fields (#1664)[https://github.com/Seldaek/monolog/pull/1664].

#### RollbarHandler

- If you redefined rollbar levels in the `$logLevels` property you must now override the
  `toRollbarLevel` method instead.

#### SamplingHandler

- The factory callable to lazy load the nested handler must now be a `Closure` instance
  and not just a `callable`.

#### SwiftMailerHandler

- Removed deprecated SwiftMailer handler, migrate to SymfonyMailerHandler instead.

#### ZendMonitorHandler

- If you redefined zend monitor levels in the `$levelMap` property you must now override the
  `toZendMonitorLevel` method instead.

#### ResettableInterface

- `reset()` now requires a void return type.

### 2.0.0

- `Monolog\Logger::API` can be used to distinguish between a Monolog `1` and `2`
  install of Monolog when writing integration code.

- Removed non-PSR-3 methods to add records, all the `add*` (e.g. `addWarning`)
  methods as well as `emerg`, `crit`, `err` and `warn`.

- DateTime are now formatted with a timezone and microseconds (unless disabled).
  Various formatters and log output might be affected, which may mess with log parsing
  in some cases.

- The `datetime` in every record array is now a DateTimeImmutable, not that you
  should have been modifying these anyway.

- The timezone is now set per Logger instance and not statically, either
  via ->setTimezone or passed in the constructor. Calls to Logger::setTimezone
  should be converted.

- `HandlerInterface` has been split off and two new interfaces now exist for
  more granular controls: `ProcessableHandlerInterface` and
  `FormattableHandlerInterface`. Handlers not extending `AbstractHandler`
  should make sure to implement the relevant interfaces.

- `HandlerInterface` now requires the `close` method to be implemented. This
  only impacts you if you implement the interface yourself, but you can extend
  the new `Monolog\Handler\Handler` base class too.

- There is no more default handler configured on empty Logger instances, if
  you were relying on that you will not get any output anymore, make sure to
  configure the handler you need.

#### LogglyFormatter

- The records' `datetime` is not sent anymore. Only `timestamp` is sent to Loggly.

#### AmqpHandler

- Log levels are not shortened to 4 characters anymore. e.g. a warning record
  will be sent using the `warning.channel` routing key instead of `warn.channel`
  as in 1.x.
- The exchange name does not default to 'log' anymore, and it is completely ignored
  now for the AMQP extension users. Only PHPAmqpLib uses it if provided.

#### RotatingFileHandler

- The file name format must now contain `{date}` and the date format must be set
  to one of the predefined FILE_PER_* constants to avoid issues with file rotation.
  See `setFilenameFormat`.

#### LogstashFormatter

- Removed Logstash V0 support
- Context/extra prefix has been removed in favor of letting users configure the exact key being sent
- Context/extra data are now sent as an object instead of single keys

#### HipChatHandler

- Removed deprecated HipChat handler, migrate to Slack and use SlackWebhookHandler or SlackHandler instead

#### SlackbotHandler

- Removed deprecated SlackbotHandler handler, use SlackWebhookHandler or SlackHandler instead

#### RavenHandler

- Removed deprecated RavenHandler handler, use sentry/sentry 2.x and their Sentry\Monolog\Handler instead

#### ElasticSearchHandler

- As support for the official Elasticsearch library was added, the former ElasticSearchHandler has been
  renamed to ElasticaHandler and the new one added as ElasticsearchHandler.
