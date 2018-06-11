<?php declare(strict_types=1);

namespace Monolog\Handler;

trait WebRequestRecognizerTrait
{

    /**
     * Checks if PHP's serving a web request
     * @return bool
     */
    protected function isWebRequest(): bool
    {
        return 'cli' !== \PHP_SAPI && 'phpdbg' !== \PHP_SAPI;
    }
}
