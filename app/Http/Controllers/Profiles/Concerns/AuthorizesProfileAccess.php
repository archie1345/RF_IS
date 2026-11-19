<?php

namespace App\Http\Controllers\Profiles\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait AuthorizesProfileAccess
{
    protected function authorizeProfileAccess(Request $request, User $user): void
    {
        $viewer = $request->user();

        if ($viewer?->isAdmin() || (int) $viewer?->id === (int) $user->id) {
            return;
        }

        abort(403);
    }
}
