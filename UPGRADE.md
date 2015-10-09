### 2.0.0

- The timezone is now set per Logger instance and not statically, either
  via ->setTimezone or passed in the constructor. Calls to Logger::setTimezone
  should be converted.

- Removed non-PSR-3 methods to add records, all the `add*` (e.g. `addWarning`)
  methods as well as `emerg`, `crit`, `err` and `warn`.
