<?php

namespace Monolog\Formatter;

class StackTraceFormatter extends LineFormatter
{
    public function __construct($format = null, $dateFormat = null)
    {
        parent::__construct($format, $dateFormat, true);
    }

    public function format(array $record)
    {
        return str_replace('\n', "\n", parent::format($record));
    }

    protected function normalizeException(\Exception $e)
    {
        return parent::normalizeException($e)."\n[stacktrace]\n".$e->getTraceAsString();
    }
}
