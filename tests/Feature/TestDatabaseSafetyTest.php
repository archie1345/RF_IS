<?php

use Symfony\Component\Process\Process;

it('refuses to bootstrap a test process configured for persistent MySQL', function (): void {
    $process = new Process(
        [
            PHP_BINARY,
            '-r',
            'require $argv[1];',
            base_path('tests/bootstrap.php'),
        ],
        base_path(),
        [
            'APP_ENV' => 'local',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'laravel',
            'DB_URL' => '',
        ],
    );

    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('Refusing to start tests');
});
