<?php

namespace Monolog;

class LogDepth
{
    /**
     * @var int Keeps track of depth to prevent infinite logging loops
     */
    private $logDepth = 0;

    public function increment(): int
    {
        $this->logDepth += 1;
        return $this->logDepth;
    }

    public function decrement(): int
    {
        $this->logDepth--;
        return $this->logDepth;
    }
}
