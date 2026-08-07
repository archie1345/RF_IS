<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            'name' => $this->requiredEnvironmentValue('ADMIN_SEED_NAME'),
            'email' => strtolower($this->requiredEnvironmentValue('ADMIN_SEED_EMAIL')),
            'password' => $this->requiredEnvironmentValue('ADMIN_SEED_PASSWORD', trimValue: false),
            'gender' => strtoupper($this->environmentValue('ADMIN_SEED_GENDER', 'MALE')),
            'phone' => $this->environmentValue('ADMIN_SEED_PHONE'),
        ];

        Validator::make($attributes, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:16', 'max:255'],
            'gender' => ['required', 'in:MALE,FEMALE'],
            'phone' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $admin = DB::transaction(function () use ($attributes): User {
            $admin = User::withTrashed()
                ->where('email', $attributes['email'])
                ->first() ?? new User();

            if ($admin->exists && $admin->trashed()) {
                $admin->restore();
            }

            $admin->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'gender' => $attributes['gender'],
                'phone' => $attributes['phone'],
                'role' => 'admin',
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            ]);
            $admin->forceFill(['email_verified_at' => now()]);
            $admin->save();

            UserRoleAssignment::query()->updateOrCreate([
                'user_id' => $admin->id,
                'role' => 'admin',
            ]);

            return $admin;
        });

        $this->command?->info(sprintf(
            'Active administrator account prepared for %s (user ID %s).',
            $admin->email,
            (string) $admin->id,
        ));
        $this->command?->warn('The administrator password was read from ADMIN_SEED_PASSWORD and was not printed.');
    }

    private function requiredEnvironmentValue(string $key, bool $trimValue = true): string
    {
        $value = getenv($key);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException(sprintf(
                'The %s environment variable is required to run AdminSeeder.',
                $key,
            ));
        }

        return $trimValue ? trim($value) : $value;
    }

    private function environmentValue(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        if ($value === false || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }
}