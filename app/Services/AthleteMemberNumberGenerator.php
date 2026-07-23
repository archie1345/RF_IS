<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AthleteMemberNumberGenerator
{
    public function generate(string|\DateTimeInterface|null $joinedAt = null): string
    {
        $joinedOn = Carbon::parse($joinedAt ?? now())->toDateString();

        return DB::transaction(function () use ($joinedOn): string {
            DB::table('member_number_sequences')->updateOrInsert(
                ['joined_on' => $joinedOn],
                ['last_sequence' => 0, 'created_at' => now(), 'updated_at' => now()],
            );

            $sequence = DB::table('member_number_sequences')
                ->where('joined_on', $joinedOn)
                ->lockForUpdate()
                ->first();

            $next = ((int) ($sequence?->last_sequence ?? 0)) + 1;

            if ($next > 9999) {
                throw new RuntimeException("Daily athlete member number capacity exceeded for {$joinedOn}.");
            }

            DB::table('member_number_sequences')
                ->where('joined_on', $joinedOn)
                ->update(['last_sequence' => $next, 'updated_at' => now()]);

            return 'G'.Carbon::parse($joinedOn)->format('Ymd').str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}