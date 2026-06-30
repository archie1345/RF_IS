<?php

namespace App\Http\Requests\Sessions\Concerns;

use App\Support\Domain\SessionStatus;
use Illuminate\Validation\Rule;

trait SessionMutationRules
{
    protected function sessionRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
            'location' => ['nullable', 'string', 'max:255'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'status' => ['required', Rule::in(SessionStatus::ALL)],
        ];
    }
}
