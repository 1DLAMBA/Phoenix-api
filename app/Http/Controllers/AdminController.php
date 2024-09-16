<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Http\Requests\StoreAdminRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateAdminRequest;
use App\Models\User;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password', 'id');

        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            return response()->json([
                'user'=>$user,
                
            ]);
        }

        return response()->json([
            'error', 'Invalid Credentials'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
       $validate = $request->validate([
        'name'=> 'required',
        'staff_id'=> 'required',
        'email'=> 'required',
        'phoneno'=> 'required',
        'password'=> 'required'
       ]);
       $user=User::create($validate);
       $user->save();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdminRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Admin $admin, string $id)
    {
        $user = Admin::findorfail($id);
        return response()->json([
            'user'=>$user
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Admin $admin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdminRequest $request, Admin $admin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Admin $admin)
    {
        //
    }
}
