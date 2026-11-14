<?php

$autoload = getenv('PACKAGE_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
require $autoload;
spl_autoload_register(function (string $class): void {
    $prefix = 'Coding1688\\Files\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
