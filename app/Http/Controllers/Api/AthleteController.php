<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\Request;

class AthleteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Athlete::whereNull('deleted_at')->with(['group', 'branch', 'parent'])->get();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $athlete = Athlete::where('athlete_id', $id)->whereNull('deleted_at')->first();

        if (! $athlete) {
            return response()->json(['message' => 'Athlete not found'], 404);
        }

        return response()->json($athlete);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bday' => 'required|date',
            'gender' => 'required|in:MALE,FEMALE',
            'height_cm' => 'required|numeric',
            'weight_kg' => 'required|numeric',
            'phone' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
            'geup' => 'required|in:GEUP_1,GEUP_2,GEUP_3,GEUP_4,GEUP_5,GEUP_6,GEUP_7,GEUP_8,GEUP_9,GEUP_10,DAN',
            'id' => 'required|exists:users,id',
            'group_id' => 'required|exists:class_groups,group_id',
            'parent_id' => 'nullable|exists:parents,parent_id',
            'branch_id' => 'required|exists:branches,branch_id',
        ]);

        $athlete = Athlete::create([
            ...$validated,
            'nik_hash' => $request->nik_hash ?? str_repeat('a', 64),
            'bpjs_hash' => $request->bpjs_hash ?? str_repeat('b', 64),
        ]);

        return response()->json($athlete, 201);
    }

    public function update(Request $request, string $id)
    {
        $athlete = Athlete::where('athlete_id', $id)->whereNull('deleted_at')->first();

        if (! $athlete) {
            return response()->json(['message' => 'Athlete not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bday' => 'sometimes|required|date',
            'gender' => 'sometimes|required|in:MALE,FEMALE',
            'height_cm' => 'sometimes|required|numeric',
            'weight_kg' => 'sometimes|required|numeric',
            'phone' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
            'geup' => 'nullable|in:GEUP_1,GEUP_2,GEUP_3,GEUP_4,GEUP_5,GEUP_6,GEUP_7,GEUP_8,GEUP_9,GEUP_10,DAN',
            'group_id' => 'sometimes|required|exists:class_groups,group_id',
            'parent_id' => 'sometimes|nullable|exists:parents,parent_id',
            'branch_id' => 'sometimes|required|exists:branches,branch_id',
            'nik' => 'sometimes|nullable|string|size:16',
            'bpjs' => 'sometimes|required|string|size:13',
        ]);

        if (isset($validated['nik'])) {
            $validated['nik_hash'] = hash('sha256', $validated['nik']);
        }

        if (isset($validated['bpjs'])) {
            $validated['bpjs_hash'] = hash('sha256', $validated['bpjs']);
        }

        unset($validated['nik'], $validated['bpjs']);

        $athlete->update($validated);

        return response()->json($athlete, 200);
    }

    public function destroy(string $id)
    {
        $athlete = Athlete::find($id);

        if (! $athlete) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $athlete->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function restore(string $id)
    {
        $athlete = Athlete::withTrashed()->find($id);

        if (! $athlete) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $athlete->restore();

        return response()->json(['message' => 'Restored']);
    }
}
