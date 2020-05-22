<?php

if (PHP_VERSION_ID >= 70400) {
    error_reporting(E_ALL & ~E_DEPRECATED);
} else {
    error_reporting(E_ALL);
}

include __DIR__.'/../vendor/autoload.php';