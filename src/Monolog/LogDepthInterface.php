<?php

namespace Monolog;

interface LogDepthInterface
{
    public function increment(): int;

    public function decrement(): int;
}
