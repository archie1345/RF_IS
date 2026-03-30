<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Group::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string',
        ]);

        return Group::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Group::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $group = Group::findOrFail($id);

        if(!$group){
            return response()->json(['message' => 'Group not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'description' => 'sometimes|nullable|string',
        ]);

        $group->update($request->all());

        return $group;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $group = Group::findOrFail($id);

        if(!$group){
            return response()->json(['message' => 'Group not found'], 404);
        }

        $group->delete();

        return response()->json(['message' => 'Group deleted successfully'], 200);
    }
}
