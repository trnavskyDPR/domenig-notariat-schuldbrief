<?php

declare(strict_types=1);

/*
 * Einstiegspunkt für Web und Tests.
 *
 * Eigener PSR-4-Autoloader: Auf dem Server braucht es weder Composer noch
 * einen vendor/-Ordner. Composer wird nur lokal für PHPUnit verwendet.
 */

date_default_timezone_set('Europe/Zurich');

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Schuldbrief\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
