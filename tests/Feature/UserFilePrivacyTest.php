<?php

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

it('allows an admin to download a private file but denies unrelated accounts', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    $athleteUser = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);
    $file = createUserFileFixture($athleteUser);

    $this->actingAs($admin)
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
