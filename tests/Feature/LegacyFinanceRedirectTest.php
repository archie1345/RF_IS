<?php

use App\Models\User;

test('legacy finance routes redirect to the payment center', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('payments.index'));
})->with([
    'admin payments' => 'admin.payments',
    'finance income' => 'admin.finance-income',
    'finance output' => 'admin.finance-output',
    'monthly dues' => 'admin.monthly-dues',
]);
