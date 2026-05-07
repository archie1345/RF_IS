<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Group::query()
            ->orderBy('group_name')
            ->get()
            ->map(fn (Group $group) => [
                'id' => (string) $group->group_id,
                'name' => $group->group_name,
                'description' => $group->description,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        return Group::create([
            'group_name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $group = Group::findOrFail($id);

        return [
            'id' => (string) $group->group_id,
            'name' => $group->group_name,
            'description' => $group->description,
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $group = Group::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
        ]);

        $group->update([
            'group_name' => $request->has('name') ? $request->string('name')->toString() : $group->group_name,
            'description' => $request->has('description') ? $request->input('description') : $group->description,
        ]);

        return [
            'id' => (string) $group->group_id,
            'name' => $group->group_name,
            'description' => $group->description,
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $group = Group::findOrFail($id);

        $group->delete();

        return response()->json(['message' => 'Group deleted successfully'], 200);
    }
}
