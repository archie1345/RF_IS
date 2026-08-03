<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('uses the global popup instead of native dialogs or retired inline alerts', function () {
    $violations = collect(File::allFiles(resource_path('js')))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['js', 'ts', 'vue'], true))
        ->mapWithKeys(function (SplFileInfo $file): array {
            $matches = [];
            $lines = preg_split('/\R/', File::get($file->getPathname())) ?: [];

            foreach ($lines as $index => $line) {
                $usesNativeDialog = preg_match(
                    '/\bwindow\.(?:alert|confirm)\s*\(|(?<![\w.])(?:alert|confirm)\s*\(/',
                    $line,
                ) === 1;
                $usesRetiredAlert = str_contains($line, 'AppAlert');

                if ($usesNativeDialog || $usesRetiredAlert) {
                    $matches[] = ($index + 1).': '.trim($line);
                }
            }

            return $matches === [] ? [] : [$file->getRelativePathname() => $matches];
        });

    expect($violations->all())->toBeEmpty();
});
