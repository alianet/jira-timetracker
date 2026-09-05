<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use App\Bootstrap;
use App\Kernel\Infrastructure\Logging\LoggerFactory;
use App\Kernel\Presentation\Http\ApplicationErrorHandler;

use function Safe\ini_set;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('Brak zależności. Uruchom: docker compose exec php composer install');
}

require_once $autoload;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

$logger = new LoggerFactory()->create($root . '/var/log/app.log', 'error');
$errorHandler = new ApplicationErrorHandler($logger, $root . '/var/log/php-error.log');
$server = $_SERVER;
$server['COOKIE'] = $_COOKIE;

try {
    $errorHandler->register($server);
    new Bootstrap($root)->run($server);
    $errorHandler->complete();
} catch (\Throwable $exception) {
    $errorHandler->handle($exception, $server);
}
