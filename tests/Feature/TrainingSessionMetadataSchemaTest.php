<?php

use App\Models\TrainingSession;
use Illuminate\Support\Facades\Schema;

it('provides the metadata schema used by one-day class sessions', function () {
    expect(Schema::hasTable('training_sessions'))->toBeTrue()
        ->and(Schema::hasColumn('training_sessions', 'metadata'))->toBeTrue();

    $session = new TrainingSession;

    expect($session->isFillable('metadata'))->toBeTrue()
        ->and($session->getCasts()['metadata'] ?? null)->toBe('array');
});

it('provides the class and session coach pivot schema', function () {
    expect(Schema::hasTable('class_group_coaches'))->toBeTrue()
        ->and(Schema::hasColumns('class_group_coaches', ['group_id', 'coach_id']))->toBeTrue()
        ->and(Schema::hasTable('training_session_coaches'))->toBeTrue()
        ->and(Schema::hasColumns('training_session_coaches', ['training_session_id', 'coach_id']))->toBeTrue();
});
