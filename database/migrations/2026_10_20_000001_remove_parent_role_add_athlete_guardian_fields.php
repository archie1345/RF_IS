<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business simplification: "parent" is no longer a login role.
 *
 * A guardian is just contact information on the athlete record (used by admin
 * for WhatsApp payment reminders). A family that needs portal access uses the
 * athlete account of the child.
 *
 * Data handling (nothing is hard-deleted):
 *  1. athletes gain guardian_name / guardian_phone / guardian_relation, copied
 *     from the previously linked parent user + parents.relation.
 *  2. Accounts that only had the parent role are suspended (reversible by an
 *     admin); accounts with other roles simply lose the parent role.
 *  3. Announcements that targeted PARENT now target ATHLETE.
 *  4. athletes.parent_id and the parents table are dropped.
 *
 * The 'parent' enum member is intentionally left in the legacy enum columns
 * (users.role, user_role_assignments.role, announcements.target_role): altering
 * enums is not portable between MySQL and SQLite, and the application layer no
 * longer accepts the value.
 */
return new class extends Migration
{
    private const ROLE_PRIORITY = ['admin', 'coach', 'athlete'];

    public function up(): void
    {
        $this->addGuardianColumns();
        $this->backfillGuardianContacts();
        $this->retireParentAccounts();
        $this->retargetParentAnnouncements();
        $this->dropParentLink();
    }

    public function down(): void
    {
        if (! Schema::hasTable('parents')) {
            Schema::create('parents', function (Blueprint $table): void {
                $table->char('parent_id', 26)->primary();
                $table->unsignedBigInteger('id')->unique('parents_user_id_unique');
                $table->string('relation', 50)->nullable();
                $table->string('occupation', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('athletes', 'parent_id')) {
            Schema::table('athletes', function (Blueprint $table): void {
                $table->char('parent_id', 26)->nullable()->index('athletes_parent_lookup_idx');
            });
        }

        // Parent links and parent-only accounts are not restored automatically:
        // guardian contacts stay on the athlete rows.
        Schema::table('athletes', function (Blueprint $table): void {
            $table->dropColumn(array_values(array_filter(
                ['guardian_name', 'guardian_phone', 'guardian_relation'],
                fn (string $column): bool => Schema::hasColumn('athletes', $column),
            )));
        });
    }

    private function addGuardianColumns(): void
    {
        Schema::table('athletes', function (Blueprint $table): void {
            if (! Schema::hasColumn('athletes', 'guardian_name')) {
                $table->string('guardian_name', 120)->nullable();
            }

            if (! Schema::hasColumn('athletes', 'guardian_phone')) {
                $table->string('guardian_phone', 30)->nullable();
            }

            if (! Schema::hasColumn('athletes', 'guardian_relation')) {
                $table->string('guardian_relation', 50)->nullable();
            }
        });
    }

    private function backfillGuardianContacts(): void
    {
        if (! Schema::hasTable('parents') || ! Schema::hasColumn('athletes', 'parent_id')) {
            return;
        }

        DB::table('athletes')
            ->join('parents', 'parents.parent_id', '=', 'athletes.parent_id')
            ->join('users', 'users.id', '=', 'parents.id')
            ->whereNotNull('athletes.parent_id')
            ->whereNull('athletes.guardian_name')
            ->orderBy('athletes.athlete_id')
            ->select([
                'athletes.athlete_id',
                'users.name as guardian_name',
                'users.phone as guardian_phone',
                'parents.relation as guardian_relation',
            ])
            ->get()
            ->each(function (object $row): void {
                DB::table('athletes')
                    ->where('athlete_id', $row->athlete_id)
                    ->update([
                        'guardian_name' => $row->guardian_name,
                        'guardian_phone' => $row->guardian_phone,
                        'guardian_relation' => $row->guardian_relation,
                    ]);
            });
    }

    private function retireParentAccounts(): void
    {
        $parentUserIds = DB::table('user_role_assignments')
            ->where('role', 'parent')
            ->pluck('user_id')
            ->merge(
                // Legacy rows that only have the users.role column and no assignment rows.
                DB::table('users')
                    ->where('role', 'parent')
                    ->whereNotIn('id', DB::table('user_role_assignments')->select('user_id'))
                    ->pluck('id'),
            )
            ->unique()
            ->values();

        foreach ($parentUserIds as $userId) {
            $remainingRoles = DB::table('user_role_assignments')
                ->where('user_id', $userId)
                ->where('role', '!=', 'parent')
                ->pluck('role')
                ->all();

            if ($remainingRoles === []) {
                // Parent-only account: keep the row for history but block sign-in.
                DB::table('users')->where('id', $userId)->update(['account_status' => 'suspended']);

                continue;
            }

            $primary = collect(self::ROLE_PRIORITY)->first(
                fn (string $role): bool => in_array($role, $remainingRoles, true),
            ) ?? $remainingRoles[0];

            DB::table('users')->where('id', $userId)->update(['role' => $primary]);
        }

        DB::table('user_role_assignments')->where('role', 'parent')->delete();
    }

    private function retargetParentAnnouncements(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'target_role')) {
            DB::table('announcements')->where('target_role', 'PARENT')->update(['target_role' => 'ATHLETE']);
        }
    }

    private function dropParentLink(): void
    {
        if (Schema::hasColumn('athletes', 'parent_id')) {
            foreach (Schema::getForeignKeys('athletes') as $foreignKey) {
                if (in_array('parent_id', $foreignKey['columns'], true)) {
                    Schema::table('athletes', fn (Blueprint $table) => $table->dropForeign(
                        $foreignKey['name'] ?? ['parent_id'],
                    ));
                }
            }

            // SQLite cannot drop a column that is still covered by an index.
            foreach (Schema::getIndexes('athletes') as $index) {
                if (! $index['primary'] && in_array('parent_id', $index['columns'], true)) {
                    Schema::table('athletes', fn (Blueprint $table) => $table->dropIndex($index['name']));
                }
            }

            Schema::table('athletes', fn (Blueprint $table) => $table->dropColumn('parent_id'));
        }

        Schema::dropIfExists('parents');
    }
};
