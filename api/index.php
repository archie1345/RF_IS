<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$storageBase = '/tmp/storage';
$bootstrapCache = '/tmp/bootstrap/cache';

foreach ([
    $storageBase.'/app/private',
    $storageBase.'/app/public',
    $storageBase.'/framework/cache/data',
    $storageBase.'/framework/cache/laravel-excel',
    $storageBase.'/framework/sessions',
    $storageBase.'/framework/views',
    $storageBase.'/logs',
    $bootstrapCache,
] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

$setDefaultEnv = static function (string $key, string $value): void {
    $current = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($current === false || $current === '') {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
};

$setDefaultEnv('APP_STORAGE_PATH', $storageBase);
$setDefaultEnv('VIEW_COMPILED_PATH', $storageBase.'/framework/views');
$setDefaultEnv('APP_SERVICES_CACHE', $bootstrapCache.'/services.php');
$setDefaultEnv('APP_PACKAGES_CACHE', $bootstrapCache.'/packages.php');
$setDefaultEnv('APP_CONFIG_CACHE', $bootstrapCache.'/config.php');
$setDefaultEnv('APP_ROUTES_CACHE', $bootstrapCache.'/routes-v7.php');
$setDefaultEnv('APP_EVENTS_CACHE', $bootstrapCache.'/events.php');
$setDefaultEnv('LOG_CHANNEL', 'stderr');
$setDefaultEnv('TRUSTED_PROXIES', '*');

$vercelUrl = $_ENV['VERCEL_URL'] ?? $_SERVER['VERCEL_URL'] ?? getenv('VERCEL_URL');
if (is_string($vercelUrl) && $vercelUrl !== '') {
    $setDefaultEnv('APP_URL', 'https://'.$vercelUrl);
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
