<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Coach::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'specialization' => 'required|string|max:255',
        ]);

        return Coach::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Coach::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $coach = Coach::findOrFail($id);

        $request->validate([
            'c_name' => 'sometimes|required|string|max:255',
            'c_phone' => 'sometimes|required|string|max:20',
            
        ]);

        $coach->update($request->all());

        return $coach;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $coach = Coach::findOrFail($id);
        $coach->delete();

        return response()->json(['message' => 'Coach deleted successfully']);
    }
}
