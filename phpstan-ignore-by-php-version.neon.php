<?php declare(strict_types = 1);

$includes = [];
if (PHP_VERSION_ID >= 80200) {
    $includes[] = __DIR__ . '/phpstan-baseline-8.2.neon';
}

$config['includes'] = $includes;

return $config;
