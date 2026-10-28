<?php

declare(strict_types=1);

$environmentValue = static function (string $name): string|false {
    if (array_key_exists($name, $_SERVER)) {
        return (string) $_SERVER[$name];
    }

    if (array_key_exists($name, $_ENV)) {
        return (string) $_ENV[$name];
    }

    return getenv($name);
};

$safeEnvironment = $environmentValue('APP_ENV') === 'testing'
    && $environmentValue('DB_CONNECTION') === 'sqlite'
    && $environmentValue('DB_DATABASE') === ':memory:'
    && in_array($environmentValue('DB_URL'), [false, ''], true)
    && ! is_file(__DIR__.'/../bootstrap/cache/config.php');

if (! $safeEnvironment) {
    fwrite(
        STDERR,
        "Refusing to start tests: use APP_ENV=testing, SQLite :memory:, an empty DB_URL, and uncached Laravel configuration. Run sh tests/run-isolated.sh.\n",
    );
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';
