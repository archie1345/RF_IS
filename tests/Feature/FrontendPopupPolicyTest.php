<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('does not use browser-native alert or confirm dialogs', function () {
    $violations = collect(File::allFiles(resource_path('js')))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['js', 'ts', 'vue'], true))
        ->mapWithKeys(function (SplFileInfo $file): array {
            $matches = [];
            $lines = preg_split('/\R/', File::get($file->getPathname())) ?: [];

            foreach ($lines as $index => $line) {
                if (preg_match('/\bwindow\.(?:alert|confirm)\s*\(|(?<![\w.])(?:alert|confirm)\s*\(/', $line) === 1) {
                    $matches[] = ($index + 1).': '.trim($line);
                }
            }

            return $matches === [] ? [] : [$file->getRelativePathname() => $matches];
        });

    expect($violations->all())->toBeEmpty();
});
