<?php

/*
 * Router for PHP's built-in server (local development only):
 *   php -S 127.0.0.1:8000 -t public router.php
 * Real files (uploaded photos) are served as they are, everything else goes to Symfony.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ('/' !== $path && is_file(__DIR__.'/public'.$path)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';

return require __DIR__.'/public/index.php';
