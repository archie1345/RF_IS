<?php

use App\Models\User;

test('legacy finance routes redirect to their current admin workspace', function (string $routeName, string $destination) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertRedirect(route($destination));
})->with([
    'admin payments' => ['admin.payments', 'payments.index'],
    'finance income' => ['admin.finance-income', 'payments.index'],
    'finance output' => ['admin.finance-output', 'payments.index'],
    'monthly dues' => ['admin.monthly-dues', 'admin.billing-settings.index'],
]);
