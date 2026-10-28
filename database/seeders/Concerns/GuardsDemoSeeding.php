<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait GuardsDemoSeeding
{
    protected function guardDemoSeeding(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo seeders are disabled in production.');
        }

        $isInMemoryTestDatabase = app()->environment('testing')
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';

        $isOptedInLocalDatabase = app()->environment('local')
            && (bool) config('app.demo_seeding_enabled');

        if (! $isInMemoryTestDatabase && ! $isOptedInLocalDatabase) {
            throw new RuntimeException(
                'Demo seeding requires SQLite :memory: tests or explicit RFIS_ALLOW_DEMO_SEEDING=true in local.',
            );
        }

        $password = config('app.demo_password');

        if (! is_string($password) || strlen($password) < 16) {
            throw new RuntimeException('Set RFIS_DEMO_PASSWORD to a development-only value of at least 16 characters.');
        }

        if ($isInMemoryTestDatabase && DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Demo tests must use SQLite :memory:.');
        }
    }
}
