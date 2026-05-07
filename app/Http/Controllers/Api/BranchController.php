<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Branch::query()
            ->orderBy('branch_name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => (string) $branch->branch_id,
                'name' => $branch->branch_name,
                'location' => $branch->location,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
        ]);

        return Branch::create([
            'branch_name' => $request->string('name')->toString(),
            'location' => $request->string('location')->toString(),
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $branch = Branch::findOrFail($id);

        return [
            'id' => (string) $branch->branch_id,
            'name' => $branch->branch_name,
            'location' => $branch->location,
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $branch = Branch::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|required|string|max:255',
        ]);

        $branch->update([
            'branch_name' => $request->has('name') ? $request->string('name')->toString() : $branch->branch_name,
            'location' => $request->has('location') ? $request->string('location')->toString() : $branch->location,
        ]);

        return [
            'id' => (string) $branch->branch_id,
            'name' => $branch->branch_name,
            'location' => $branch->location,
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }
}
