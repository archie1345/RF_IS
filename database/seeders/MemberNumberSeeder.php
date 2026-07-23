<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Services\MemberNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberNumberSeeder extends Seeder
{
    public function run(): void
    {
        $joinedDates = [
            'multirole@rfis.test' => '2022-01-15',
            'athlete@rfis.test' => '2024-07-01',
            'nadia@rfis.test' => '2024-07-01',
            'rafi@rfis.test' => '2025-01-10',
            'putri@rfis.test' => '2025-01-10',
            'child@rfis.test' => '2026-07-23',
        ];

        DB::transaction(function () use ($joinedDates): void {
            DB::table('athletes')->update(['member_number' => null]);
            DB::table('member_number_sequences')->delete();

            $athletes = Athlete::query()
                ->with('user:id,email')
                ->get()
                ->sortBy(function (Athlete $athlete) use ($joinedDates): string {
                    $email = $athlete->user?->email ?? '';
                    $joinedAt = $joinedDates[$email]
                        ?? $athlete->created_at?->toDateString()
                        ?? now()->toDateString();

                    return $joinedAt.'|'.$email;
                });

            foreach ($athletes as $athlete) {
                $joinedAt = $joinedDates[$athlete->user?->email ?? '']
                    ?? $athlete->created_at?->toDateString()
                    ?? now()->toDateString();

                $athlete->forceFill([
                    'joined_at' => $joinedAt,
                    'member_number' => app(MemberNumberGenerator::class)->generate($joinedAt),
                ])->saveQuietly();
            }
        });
    }
}
