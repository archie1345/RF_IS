<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createUserFileFixture(User $owner): UserFile
{
    $path = 'user-files/'.$owner->id.'/fixture.pdf';
    Storage::disk(UserFile::DISK_PRIVATE)->put($path, 'private document');

    return UserFile::query()->create([
        'user_id' => $owner->id,
        'file_type' => 'CERTIFICATE',
        'original_name' => 'certificate.pdf',
        'file_path' => $path,
        'disk' => UserFile::DISK_PRIVATE,
        'mime_type' => 'application/pdf',
        'size_bytes' => 16,
    ]);
}

beforeEach(function (): void {
    Storage::fake(UserFile::DISK_PRIVATE);
    Storage::fake(UserFile::DISK_PUBLIC);
});

it('stores new achievement documents on private storage', function () {
    $athlete = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($athlete)
        ->post(route('achievements.store'), [
            'championship_name' => 'Private Document Cup',
            'medal' => 'GOLD',
            'event_date' => today()->toDateString(),
            'file' => UploadedFile::fake()->create('result.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect(route('achievements.index'));

    $file = UserFile::query()->where('user_id', $athlete->id)->sole();

    expect($file->storageDisk())->toBe(UserFile::DISK_PRIVATE);
    Storage::disk(UserFile::DISK_PRIVATE)->assertExists($file->file_path);
    Storage::disk(UserFile::DISK_PUBLIC)->assertMissing($file->file_path);
});

it('allows an owner to download their private file', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $file = createUserFileFixture($owner);

    $this->actingAs($owner)
        ->get(route('user-files.download', $file))
        ->assertOk()
        ->assertDownload('certificate.pdf');
});

it('allows a linked parent to download a child file but denies unrelated accounts', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Private File Branch',
        'location' => 'Malang',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Private File Group',
    ]);
    $parentUser = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);
    $parent = ParentProfile::query()->create([
        'id' => $parentUser->id,
        'relation' => 'guardian',
    ]);
    $childUser = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);
    Athlete::query()->create([
        'id' => $childUser->id,
        'parent_id' => $parent->parent_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'geup' => 'GEUP_5',
        'nik_hash' => hash('sha256', 'private-child-nik'),
        'bpjs_hash' => hash('sha256', 'private-child-bpjs'),
    ]);
    $file = createUserFileFixture($childUser);

    $this->actingAs($parentUser)
        ->get(route('user-files.download', $file))
        ->assertOk()
        ->assertDownload('certificate.pdf');

    $unrelated = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($unrelated)
        ->get(route('user-files.download', $file))
        ->assertForbidden();
});
