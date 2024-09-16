<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;



class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('doctors', 'nurses', 'clients')->get();
        return response()->json([
            'users' => $users
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
        $check = User::where('email', $validate['email'])->first();
        if ($check) {
            return response()->json([
                'error' => 'An account is already registered with this email'
              ], 409);
        } else {
            $user = User::create($validate);
            // Save the user to the database
            $user->save();
            return response()->json([
                'success' => 'Registered!',
                'user' => $user
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
                'user' => $user,

            ]);
        }

        return response($body = 'invalid credentials', $status = 400, $headers = ['error']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('doctors', 'nurses', 'clients.appointments.doctor.user')->findorfail($id);
        return response()->json([
            'user' => $user
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
