<?php declare(strict_types=1);

namespace Monolog;

use Psr\Clock\ClockInterface;
use Monolog\JsonSerializableDateTimeImmutable;

class Clock implements ClockInterface
{
    private bool $useMicroseconds;
    private ?\DateTimeZone $timezone;
    private JsonSerializableDateTimeImmutable $fixedTime;

    public function __construct(bool $useMicroseconds = true, ?\DateTimeZone $timezone = null)
    {
        $this->useMicroseconds = $useMicroseconds;
        $this->timezone = $timezone;
    }

    public function now(): JsonSerializableDateTimeImmutable
    {
        return $this->fixedTime ?? new JsonSerializableDateTimeImmutable($this->useMicroseconds, $this->timezone);
    }

    public function setUseMicroseconds(bool $useMicroseconds): void
    {
        $this->useMicroseconds = $useMicroseconds;
    }

    public function setTimezone(?\DateTimeZone $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function setFixedTime(JsonSerializableDateTimeImmutable $fixedTime): void
    {
        $this->fixedTime = $fixedTime;
    }
}
