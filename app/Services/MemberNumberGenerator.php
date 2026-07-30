<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MemberNumberGenerator
{
    public function generate(CarbonInterface|string|null $joinedAt = null): string
    {
        $joinedOn = Carbon::parse($joinedAt ?? now())->startOfDay();
        $date = $joinedOn->toDateString();

        return DB::transaction(function () use ($date, $joinedOn): string {
            DB::table('member_number_sequences')->insertOrIgnore([
                'joined_on' => $date,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('member_number_sequences')
                ->where('joined_on', $date)
                ->lockForUpdate()
                ->first();

            $next = ((int) ($row?->last_sequence ?? 0)) + 1;

            if ($next > 9999) {
                throw new RuntimeException("Member number capacity exceeded for {$date}.");
            }

            DB::table('member_number_sequences')
                ->where('joined_on', $date)
                ->update([
                    'last_sequence' => $next,
                    'updated_at' => now(),
                ]);

            return 'G'.$joinedOn->format('Ymd').str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }, 3);
    }
}
