### 2.0.0

- The timezone is now set per Logger instance and not statically, either
  via ->setTimezone or passed in the constructor. Calls to Logger::setTimezone
  should be converted.

- Removed non-PSR-3 methods to add records, all the `add*` (e.g. `addWarning`)
  methods as well as `emerg`, `crit`, `err` and `warn`.

- `HandlerInterface` has been split off and two new interfaces now exist for
  more granular controls: `ProcessableHandlerInterface` and
  `FormattableHandlerInterface`. Handlers not extending `AbstractHandler`
  should make sure to implement the relevant interfaces.

- `HandlerInterface` now requires the `close` method to be implemented. This
  only impacts you if you implement the interface yourself, but you can extend
  the new `Monolog\Handler\Handler` base class.
