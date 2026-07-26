<?php

it('surfaces validation server network and browser errors through the global popup', function () {
    $host = file_get_contents(resource_path('js/components/shared/GlobalPopupHost.vue'));

    expect($host)
        ->toContain("router.on('error'")
        ->toContain("router.on('invalid'")
        ->toContain("router.on('exception'")
        ->toContain('event.detail.exception')
        ->not->toContain('event.detail.error')
        ->toContain("window.addEventListener('error'")
        ->toContain("window.addEventListener('unhandledrejection'")
        ->toContain("window.addEventListener('offline'")
        ->toContain('Data belum valid')
        ->toContain('Respons server tidak dapat diproses');
});
