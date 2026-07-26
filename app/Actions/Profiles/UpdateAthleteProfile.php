<?php

namespace App\Actions\Profiles;

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAthleteProfile
{
    public function handle(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $athlete = $user->athleteProfile()->first();
            $branchId = $data['branch_id'] ?? $athlete?->branch_id ?? Branch::query()->value('branch_id');
            $groupId = $data['group_id'] ?? $athlete?->group_id ?? Group::query()->value('group_id');

            if (! $branchId || ! $groupId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Create at least one branch and one group before saving an athlete profile.',
                    'group_id' => 'Create at least one branch and one group before saving an athlete profile.',
                ]);
            }

            $payload = [
                'height_cm' => $data['height_cm'] ?? 0,
                'weight_kg' => $data['weight_kg'] ?? 0,
                'geup' => $data['geup'],
                'alamat' => $data['alamat'] ?? null,
                'school_origin' => $data['school_origin'] ?? null,
                'branch_id' => $branchId,
                'group_id' => $groupId,
            ];

            if (array_key_exists('nik', $data)) {
                $nik = $this->nullableString($data['nik'] ?? null);
                $payload['nik_hash'] = $nik ? hash('sha256', preg_replace('/\s+/', '', $nik)) : null;
                $payload['nik_ciphertext'] = $nik;
            }

            if (array_key_exists('bpjs', $data)) {
                $bpjs = $this->nullableString($data['bpjs'] ?? null);
                $payload['bpjs_hash'] = $bpjs ? hash('sha256', preg_replace('/\s+/', '', $bpjs)) : null;
                $payload['bpjs_ciphertext'] = $bpjs;
            }

            Athlete::query()->updateOrCreate(['id' => $user->id], $payload);
        });
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
