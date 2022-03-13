# Log message structure

Within monolog log messages are passed around as arrays, for example to processors or handlers.
The table below describes which keys are always available for every log message.

key        | type                      | description
-----------|---------------------------|-------------------------------------------------------------------------------
message    | string                    | The log message. When the `PsrLogMessageProcessor` is used this string may contain placeholders that will be replaced by variables from the context, e.g., "User {username} logged in" with `['username' => 'John']` as context will be written as "User John logged in".
level      | int                       | Severity of the log message. See log levels described in [01-usage.md](01-usage.md#log-levels).
level_name | string                    | String representation of log level.
context    | array                     | Arbitrary data passed with the construction of the message. For example the username of the current user or their IP address.
channel    | string                    | The channel this message was logged to. This is the name that was passed when the logger was created with `new Logger($channel)`.
datetime   | Monolog\DateTimeImmutable | Date and time when the message was logged. Class extends `\DateTimeImmutable`.
extra      | array                     | A placeholder array where processors can put additional data. Always available, but empty if there are no processors registered.

At first glance `context` and `extra` look very similar, and they are in the sense that they both carry arbitrary data that is related to the log message somehow.
The main difference is that `context` can be supplied in user land (it is the 3rd parameter to `Logger::addRecord()`) whereas `extra` is internal only and can be filled by processors.
The reason processors write to `extra` and not to `context` is to prevent overriding any user-provided data in `context`.
