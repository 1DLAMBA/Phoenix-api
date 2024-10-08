<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $client = Client::with('user')->get();
        return response()->json([
            'client'=>$client
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreClientRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            $client = Client::create($validatedData);
            $client->save();

            return response()->json([
                'Success'=> 'Registered as a client'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client, string $id)
    {
        $client = Client::with('user', 'appointments.doctor.user')->findOrFail($id);
        return response()->json([
            'client'=>$client
        ]);
    }

     public function getClient(string $id)
    {
        $user = Client::with('appointments')->where('user_id', $id)->first();
        return response()->json([
            'client' => $user
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        //
    }
}
