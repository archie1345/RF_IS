<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

test('coach cannot promote themself to admin through account management', function () {
    $coach = User::factory()->create(['role' => 'coach']);

    $this->actingAs($coach)
        ->put(route('admin.accounts.update', $coach), [
            'name' => $coach->name,
            'email' => $coach->email,
            'roles' => ['admin', 'coach'],
            'status' => 'active',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertForbidden();

    expect($coach->fresh()->isAdmin())->toBeFalse();
});

test('non-admin users cannot import CSV data', function () {
    $coach = User::factory()->create(['role' => 'coach']);

    $this->actingAs($coach)
        ->post(route('admin.data-transfer.import'), [
            'entity' => 'athletes',
            'file' => UploadedFile::fake()->createWithContent('athletes.csv', "name,email,role\nTest,test@example.com,athlete\n"),
        ])
        ->assertForbidden();
});
