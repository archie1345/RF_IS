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
        DB::transaction(function (): void {
            DB::table('athletes')->update(['member_number' => null]);
            DB::table('member_number_sequences')->delete();

            Athlete::query()
                ->with('user:id,email')
                ->get()
                ->sortBy(fn (Athlete $athlete): string => implode('|', [
                    $athlete->joined_at?->toDateString() ?? $athlete->created_at?->toDateString() ?? now()->toDateString(),
                    $athlete->user?->email ?? '',
                    (string) $athlete->athlete_id,
                ]))
                ->each(function (Athlete $athlete): void {
                    $joinedAt = $athlete->joined_at
                        ?? $athlete->created_at
                        ?? now();

                    $athlete->forceFill([
                        'joined_at' => $joinedAt,
                        'member_number' => app(MemberNumberGenerator::class)->generate($joinedAt),
                    ])->saveQuietly();
                });
        });

        $this->command?->info('Athlete member numbers regenerated from persisted joining dates.');
    }
}