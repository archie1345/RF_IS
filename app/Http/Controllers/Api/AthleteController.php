<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\SoftDeletes;

class AthleteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Athlete::whereNull('deleted_at')->get();
    }
    
    
    // public function index()
    // {
    //     return Athlete::with(['group','branch','parent'])->get();
    // }
    
    public function show(string $id)
    {
        $athlete = Athlete::where('aid', $id)->whereNull('deleted_at')->first();

        if (!$athlete) {
            return response()->json(['message' => 'Athlete not found'], 404);
        }

        return response()->json($athlete);
    }

    public function store(Request $request)
    {
        $athlete = Athlete::create([
            'name' => $request->name,
            'bday' => $request->bday,
            'gender' => $request->gender,
            'height_cm' => $request->height_cm,
            'weight_kg' => $request->weight_kg,
            'nik_hash' => $request->nik_hash ?? str_repeat('a', 64),
            'bpjs_hash' => $request->bpjs_hash ?? str_repeat('b', 64),
            'phone' => $request->phone ?? NULL,
            'alamat' => $request->alamat ?? NULL,
            'geup' => $request->geup ?? "GEUP_10",
            'id' => $request->id,
            'gid' => $request->gid ?? 1,
            'pid' => $request->pid ?? NULL,
            'brid' => $request->brid ?? 1,
        ]);
        return response()->json($athlete, 201);
    }

    public function update(Request $request, string $id)
    {
        $athlete = Athlete::where('aid', $id)->whereNull('deleted_at')->first();

        if (!$athlete) {
            return response()->json(['message' => 'Athlete not found'], 404);
        }

        $athlete->update($request->only(['name','bday','gender','height_cm','weight_kg','phone','alamat','geup','gid','pid','brid']));

        return response()->json($athlete);
    }

    public function destroy(string $id)
    {
        $athlete = Athlete::find($id);

        if (!$athlete) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $athlete->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
