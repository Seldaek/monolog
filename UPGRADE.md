### 2.0.0

- The timezone is now set per Logger instance and not statically, either
  via ->setTimezone or passed in the constructor. Calls to Logger::setTimezone
  should be converted.
