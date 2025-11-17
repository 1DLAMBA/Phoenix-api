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
        $query = Client::with(['user', 'doctors.user']);
        
        // Search functionality
        if (request()->has('search') && !empty(request('search'))) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('phoneno', 'like', "%{$search}%");
                })->orWhereHas('doctors.user', function($doctorQuery) use ($search) {
                    $doctorQuery->where('name', 'like', "%{$search}%");
                });
            });
        }
        
        // Pagination
        $perPage = request('per_page', 10);
        $page = request('page', 1);
        
        $clients = $query->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'client' => $clients->items(),
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'last_page' => $clients->lastPage(),
                'from' => $clients->firstItem(),
                'to' => $clients->lastItem()
            ]
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
