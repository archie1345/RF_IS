<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Display a listing of the resource. READ
     */
    public function index()
    {
        return User::all();
    }

    /**
     * Store a newly created resource in storage. CREATE
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:ADMIN,COACH,ATHLETE'
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role']
        ]);
        return response()->json($user, 201);
    }

    /**
     * Display the specified resource. READ
     */
    public function show(string $id)
    {
        return User::findOrFail($id);
    }

    /**
     * Update the specified resource in storage. UPDATE
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $data = $request->all();

        if(isset($request->password)){
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage. DELETE
     */
    public function destroy(string $id)
    {
        User::destroy($id);

        return response()->json(['message'=>'deleted']);
    }
}
