<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $group = Group::create([
            'group_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created group', $group, ['group_name' => $group->group_name]);

        return redirect()->route('admin.index');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $group->update([
            'group_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated group', $group, ['group_name' => $group->group_name]);

        return redirect()->route('admin.index');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted group', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return redirect()->route('admin.index');
    }
}
