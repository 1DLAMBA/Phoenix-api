<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users=User::with('doctors', 'nurses', 'clients')->get();
        return response()->json([
            'users'=>$users
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email',
            'password' => 'required|string',
            'phoneno' => 'required|string',
            'user_type' => 'required|string',
            'gender' => 'required|string',
            'passport' => '',
        ]);

       if ($validate){
        $user = User::create($validate);
        $user->save();
        // Save the user to the database
        return response()->json([
            'success'=> 'Registered!'

        ]);
       } 
    }

    /**
     * Store a newly created resource in storage.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password', 'id');

        if (auth()->attempt($credentials)) {
            $user = auth()->user()->load('doctors', 'nurses', 'clients', 'sentMessages', 'receivedMessages');;
            return response()->json([
                'user'=>$user,
                
            ]);
        }

        return response()->json([
            'error', 'Invalid Credentials'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user=User::with('doctors', 'nurses', 'clients')->findorfail($id);
        return response()->json([
            'user'=>$user
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
