<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $branch = Branch::create([
            'branch_name' => $validated['name'],
            'location' => $validated['location'],
        ]);

        ActivityLogger::log($request, 'admin.branch.created', 'admin', 'Created branch', $branch, ['branch_name' => $branch->branch_name]);

        return redirect()->route('admin.index');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $branch->update([
            'branch_name' => $validated['name'],
            'location' => $validated['location'],
        ]);

        ActivityLogger::log($request, 'admin.branch.updated', 'admin', 'Updated branch', $branch, ['branch_name' => $branch->branch_name]);

        return redirect()->route('admin.index');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        ActivityLogger::log($request, 'admin.branch.deleted', 'admin', 'Deleted branch', $branch, ['branch_name' => $branch->branch_name]);
        $branch->delete();

        return redirect()->route('admin.index');
    }
}
