<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->select('id', 'password')
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $password = (string) $user->password;

                $isBcryptHash = str_starts_with($password, '$2y$')
                    || str_starts_with($password, '$2a$')
                    || str_starts_with($password, '$2b$');

                if ($isBcryptHash || $password === '') {
                    return;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'password' => Hash::make($password),
                        'updated_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
