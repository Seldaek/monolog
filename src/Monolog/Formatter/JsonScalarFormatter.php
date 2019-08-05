<?php declare(strict_types=1);

namespace Monolog\Formatter;

class JsonScalarFormatter
{
    /** @var ScalarFormatter */
    private $scalarFormatter;

    /**
     * @param ScalarFormatter $scalarFormatter
     */
    public function __construct(ScalarFormatter $scalarFormatter)
    {
        $this->scalarFormatter = $scalarFormatter;
    }

    /**
     * @param array $record
     * @return string
     */
    public function format(array $record): string
    {
        $record = $this->scalarFormatter->format($record);

        return json_encode($record);
    }
}
