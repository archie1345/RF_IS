<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('browser http errors render the dedicated status page', function () {
    $this->get('/missing-http-status-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 404)
            ->where('statusText', 'Not Found'));
});

test('explicit browser errors use the same dedicated status page', function () {
    Route::middleware('web')->get('/testing/forbidden-status-page', fn () => abort(403));

    $this->get('/testing/forbidden-status-page')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 403)
            ->where('statusText', 'Forbidden'));
});

test('json errors remain machine readable instead of rendering inertia', function () {
    $this->getJson('/missing-json-status-endpoint')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
