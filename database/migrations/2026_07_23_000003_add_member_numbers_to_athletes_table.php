<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_number_sequences')) {
            Schema::create('member_number_sequences', function (Blueprint $table): void {
                $table->date('joined_on')->primary();
                $table->unsignedSmallInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('athletes')) {
            return;
        }

        Schema::table('athletes', function (Blueprint $table): void {
            if (! Schema::hasColumn('athletes', 'member_number')) {
                $table->string('member_number', 13)->nullable()->after('athlete_id');
                $table->unique('member_number', 'athletes_member_number_unique');
            }

            if (! Schema::hasColumn('athletes', 'joined_at')) {
                $table->date('joined_at')->nullable()->after('member_number')->index();
            }
        });

        $sequences = [];
        $athletes = DB::table('athletes')
            ->select(['athlete_id', 'created_at'])
            ->orderBy('created_at')
            ->orderBy('athlete_id')
            ->get();

        foreach ($athletes as $athlete) {
            $joinedOn = Carbon::parse($athlete->created_at ?? now())->toDateString();
            $sequence = ($sequences[$joinedOn] ?? 0) + 1;
            $sequences[$joinedOn] = $sequence;

            DB::table('athletes')
                ->where('athlete_id', $athlete->athlete_id)
                ->update([
                    'joined_at' => $joinedOn,
                    'member_number' => 'G'.Carbon::parse($joinedOn)->format('Ymd').str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                ]);
        }

        foreach ($sequences as $joinedOn => $lastSequence) {
            DB::table('member_number_sequences')->updateOrInsert(
                ['joined_on' => $joinedOn],
                ['last_sequence' => $lastSequence, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('athletes')) {
            Schema::table('athletes', function (Blueprint $table): void {
                if (Schema::hasColumn('athletes', 'member_number')) {
                    $table->dropUnique('athletes_member_number_unique');
                    $table->dropColumn('member_number');
                }

                if (Schema::hasColumn('athletes', 'joined_at')) {
                    $table->dropColumn('joined_at');
                }
            });
        }

        Schema::dropIfExists('member_number_sequences');
    }
};
