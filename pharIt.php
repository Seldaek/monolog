#!/usr/bin/env php
<?php

$phar = new Phar('monolog.phar', 0, 'logger');
$phar->setSignatureAlgorithm(Phar::SHA1);

$phar->startBuffering();

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/src/Monolog', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    if (false !== strpos($file->getRealPath(), '.git')) {
        continue;
    }

    $path = str_replace(realpath(__DIR__).'/', '', $file->getRealPath());
    $phar->addFile($file->getRealPath(), $path);

    echo "$path\n";
}

$phar->addFile('src/Monolog/Logger.php');

$phar->setDefaultStub('src/Monolog/Logger.php', 'Logger.php');

$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);

unset($phar);