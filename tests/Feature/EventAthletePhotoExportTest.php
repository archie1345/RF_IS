<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can download registered athlete 3x4 photos as a zip with manifest', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $athleteUser = User::factory()->create([
        'name' => 'Ayu Pratama',
        'email' => 'ayu@example.test',
        'role' => 'athlete',
    ]);
    $branch = Branch::query()->create(['branch_name' => 'Central', 'is_active' => true]);
    $group = Group::query()->create([
        'group_name' => 'Junior Competition',
        'branch_id' => $branch->branch_id,
        'is_active' => true,
    ]);
    $athlete = Athlete::query()->create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 155,
        'weight_kg' => 48,
        'nik_hash' => hash('sha256', 'ayu-nik'),
        'bpjs_hash' => hash('sha256', 'ayu-bpjs'),
        'geup' => 'GEUP_5',
    ]);
    $photo = UploadedFile::fake()->image('ayu.jpg', 600, 800);
    $photoPath = 'profiles/ayu-pratama.jpg';
    Storage::disk('public')->put($photoPath, file_get_contents($photo->getRealPath()));
    UserProfile::query()->create([
        'user_id' => $athleteUser->id,
        'profile_picture_path' => $photoPath,
        'bio' => null,
    ]);
    $event = Event::query()->create([
        'e_name' => 'Jakarta Open',
        'e_date' => '2026-09-12',
        'location' => 'Jakarta',
        'level' => 'NATIONAL',
        'entry_fee' => 100000,
        'max_slots' => 50,
        'status' => 'SCHEDULED',
    ]);
    EventRegistration::query()->create([
        'event_id' => $event->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'status' => 'CONFIRMED',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('championships.photos', $event))
        ->assertOk()
        ->assertDownload('jakarta-open-foto-atlet-3x4.zip');

    $archivePath = $response->baseResponse->getFile()->getPathname();
    $archive = new \ZipArchive;
    expect($archive->open($archivePath))->toBeTrue()
        ->and($archive->locateName('manifest.csv'))->not->toBeFalse()
        ->and($archive->locateName('README.txt'))->not->toBeFalse();

    $photoEntries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $entry = $archive->getNameIndex($index);
        if (is_string($entry) && str_starts_with($entry, 'photos/')) {
            $photoEntries[] = $entry;
        }
    }

    expect($photoEntries)->toHaveCount(1)
        ->and($photoEntries[0])->toContain('ayu-pratama');

    $manifest = $archive->getFromName('manifest.csv');
    expect($manifest)->toContain('Ayu Pratama')
        ->and($manifest)->toContain('Included');

    $archive->close();
});

test('non admin cannot download the event athlete photo archive', function () {
    $athlete = User::factory()->create(['role' => 'athlete']);
    $event = Event::query()->create([
        'e_name' => 'Restricted Event',
        'e_date' => '2026-09-12',
        'location' => 'Jakarta',
        'level' => 'LOCAL',
        'entry_fee' => 0,
        'max_slots' => 20,
        'status' => 'SCHEDULED',
    ]);

    $this->actingAs($athlete)
        ->get(route('championships.photos', $event))
        ->assertForbidden();
});
