<?php declare(strict_types=1);

namespace Monolog\Handler;

abstract class AbstractBrowserHandler extends AbstractProcessingHandler
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
