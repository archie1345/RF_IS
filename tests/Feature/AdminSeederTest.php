<?php

use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin seeder creates or restores one active verified administrator', function () {
    $keys = [
        'ADMIN_SEED_NAME',
        'ADMIN_SEED_EMAIL',
        'ADMIN_SEED_PASSWORD',
        'ADMIN_SEED_GENDER',
        'ADMIN_SEED_PHONE',
    ];
    $originalValues = collect($keys)->mapWithKeys(fn (string $key): array => [$key => getenv($key)]);

    try {
        putenv('ADMIN_SEED_NAME=Production Administrator');
        putenv('ADMIN_SEED_EMAIL=admin@example.test');
        putenv('ADMIN_SEED_PASSWORD=A-long-production-password-1234');
        putenv('ADMIN_SEED_GENDER=FEMALE');
        putenv('ADMIN_SEED_PHONE=081234567890');

        $existing = User::factory()->create([
            'name' => 'Old Administrator',
            'email' => 'admin@example.test',
            'password' => 'old-password',
            'gender' => 'MALE',
            'role' => 'athlete',
            'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
            'email_verified_at' => null,
        ]);
        $existing->delete();

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        expect(User::withTrashed()->where('email', 'admin@example.test')->count())->toBe(1)
            ->and($admin->name)->toBe('Production Administrator')
            ->and($admin->gender)->toBe('FEMALE')
            ->and($admin->phone)->toBe('081234567890')
            ->and($admin->role)->toBe('admin')
            ->and($admin->isActiveAccount())->toBeTrue()
            ->and($admin->email_verified_at)->not->toBeNull()
            ->and(Hash::check('A-long-production-password-1234', $admin->password))->toBeTrue()
            ->and(UserRoleAssignment::query()
                ->where('user_id', $admin->id)
                ->where('role', 'admin')
                ->exists())->toBeTrue();
    } finally {
        foreach ($originalValues as $key => $value) {
            $value === false ? putenv($key) : putenv($key.'='.$value);
        }
    }
});
