<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Http\Requests\StoreNurseRequest;
use App\Http\Requests\UpdateNurseRequest;

class NurseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Nurse::with('user');
        
        // Search functionality
        if (request()->has('search') && !empty(request('search'))) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('phoneno', 'like', "%{$search}%");
                })->orWhere('license_number', 'like', "%{$search}%")
                  ->orWhere('specialization', 'like', "%{$search}%");
            });
        }
        
        // Pagination
        $perPage = request('per_page', 10);
        $page = request('page', 1);
        
        $nurses = $query->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'nurse' => $nurses->items(),
            'pagination' => [
                'current_page' => $nurses->currentPage(),
                'per_page' => $nurses->perPage(),
                'total' => $nurses->total(),
                'last_page' => $nurses->lastPage(),
                'from' => $nurses->firstItem(),
                'to' => $nurses->lastItem()
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreNurseRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            $nurse = Nurse::create($validatedData);
            $nurse->save();

            return response()->json([
                'success'=>'registered as a nurse'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNurseRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Nurse $nurse, $id)
    {
        $nurse = Nurse::with('user')->findOrFail($id)->first();
        return response()->json([
            'nurse'=>$nurse
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Nurse $nurse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNurseRequest $request, Nurse $nurse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Nurse $nurse)
    {
        //
    }
}
