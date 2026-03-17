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
        return Athlete::with(['group','branch','parent'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $athelete = Athlete::create($request->all());
        return response()->json($athelete, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Athlete::with(['group','branch','parent'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $athelete = Athlete::findOrFail($id);

        $athelete->update($request->all());

        return response()->json($athelete);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Athlete::destroy($id);

        return response()->json(['message'=>'deleted']);
    }
}
