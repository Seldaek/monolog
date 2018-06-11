<?php declare(strict_types=1);

namespace Monolog\Handler;

trait WebRequestRecognizerTrait
{

    /**
     * Checks if PHP's serving a web request
     * @return bool
     */
    public function isWebRequest(): bool
    {
        return 'cli' !== php_sapi_name();
    }
}
