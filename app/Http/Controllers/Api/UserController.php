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
        $user = User::create([
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role
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
