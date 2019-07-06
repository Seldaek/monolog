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
