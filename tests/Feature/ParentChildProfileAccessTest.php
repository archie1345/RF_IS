<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function makeLinkedParentChildForProfileAccessTest(): array
{
    $branch = Branch::create(['branch_name' => 'Profile Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Profile Group']);

    $parentUser = User::factory()->create(['role' => 'parent']);
    $parent = ParentProfile::create(['id' => $parentUser->id, 'relation' => 'mother']);

    $childUser = User::factory()->create([
        'name' => 'Linked Child',
        'role' => 'athlete',
        'gender' => 'MALE',
    ]);

    $athlete = Athlete::create([
        'id' => $childUser->id,
        'parent_id' => $parent->parent_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 145,
        'weight_kg' => 42,
        'nik_hash' => hash('sha256', 'linked-child-nik'),
        'bpjs_hash' => hash('sha256', 'linked-child-bpjs'),
        'geup' => 'GEUP_3',
    ]);

    return [$parentUser, $childUser, $athlete, $branch, $group];
}

test('linked parent can open the full child profile management page', function () {
    [$parentUser, $childUser] = makeLinkedParentChildForProfileAccessTest();

    $this->actingAs($parentUser)
        ->get(route('athletes.show', $childUser))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('profiles/ProfileDetailsPage')
            ->where('user.id', $childUser->id)
            ->where('canEditAccount', true)
            ->where('canEditRoleProfiles', true)
            ->where('accountUpdateUrl', '/users/'.$childUser->id.'/account')
            ->where('profileUpdateUrl', '/users/'.$childUser->id.'/profile')
            ->where('passwordUpdateUrl', '/users/'.$childUser->id.'/password'));
});

test('linked parent can update child account athlete profile records and password', function () {
    [$parentUser, $childUser, $athlete, $branch, $group] = makeLinkedParentChildForProfileAccessTest();

    $this->actingAs($parentUser)
        ->patch(route('users.account.update', $childUser), [
            'name' => 'Updated Child',
            'email' => 'updated-child@example.com',
            'gender' => 'FEMALE',
            'bday' => '2012-05-01',
            'phone' => '08123456789',
        ])
        ->assertRedirect();

    expect($childUser->refresh())
        ->name->toBe('Updated Child')
        ->email->toBe('updated-child@example.com');

    $this->actingAs($parentUser)
        ->put(route('users.athlete-profile.update', $childUser), [
            'height_cm' => 152,
            'weight_kg' => 48,
            'geup' => 'GEUP_2',
            'gender' => 'FEMALE',
            'bday' => '2012-05-01',
            'phone' => '08129876543',
            'nik' => '3174000011110001',
            'bpjs' => '0001234567890',
            'alamat' => 'Updated child address',
            'branch_id' => $branch->branch_id,
            'group_id' => $group->group_id,
        ])
        ->assertRedirect();

    $athlete->refresh();

    expect((float) $athlete->height_cm)->toBe(152.0);
    expect((float) $athlete->weight_kg)->toBe(48.0);
    expect($athlete)
        ->geup->toBe('GEUP_2')
        ->alamat->toBe('Updated child address')
        ->nik_ciphertext->toBe('3174000011110001')
        ->bpjs_ciphertext->toBe('0001234567890');

    $this->actingAs($parentUser)
        ->post(route('users.certifications.store', $childUser), [
            'cert_type' => 'BELT',
            'title' => 'Green Belt',
            'issuer' => 'RFIS',
            'certified_at' => '2026-01-15',
        ])
        ->assertRedirect();

    $this->actingAs($parentUser)
        ->post(route('users.achievements.store', $childUser), [
            'championship_name' => 'Jakarta Open',
            'medal' => 'GOLD',
            'event_date' => '2026-02-20',
        ])
        ->assertRedirect();

    expect($childUser->certifications()->where('title', 'Green Belt')->exists())->toBeTrue();
    expect($childUser->achievements()->where('championship_name', 'Jakarta Open')->exists())->toBeTrue();

    $this->actingAs($parentUser)
        ->put(route('users.password.update', $childUser), [
            'password' => 'new-child-password',
            'password_confirmation' => 'new-child-password',
        ])
        ->assertRedirect();

    expect(Hash::check('new-child-password', $childUser->refresh()->password))->toBeTrue();
});

test('parent cannot access an unlinked child profile', function () {
    [$parentUser] = makeLinkedParentChildForProfileAccessTest();
    [, $unlinkedChildUser] = makeLinkedParentChildForProfileAccessTest();

    $this->actingAs($parentUser)
        ->get(route('athletes.show', $unlinkedChildUser))
        ->assertForbidden();
});
