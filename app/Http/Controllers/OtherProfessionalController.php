<?php

namespace App\Http\Controllers;

use App\Models\OtherProfessional;
use App\Http\Requests\StoreOtherProfessionalRequest;
use App\Http\Requests\UpdateOtherProfessionalRequest;

class OtherProfessionalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = OtherProfessional::with('user');
        
        // Search functionality
        if (request()->has('search') && !empty(request('search'))) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('phoneno', 'like', "%{$search}%");
                })->orWhere('license_number', 'like', "%{$search}%")
                  ->orWhere('specialization', 'like', "%{$search}%")
                  ->orWhere('professional_type', 'like', "%{$search}%");
            });
        }
        
        // Pagination
        $perPage = request('per_page', 10);
        $page = request('page', 1);
        
        $otherProfessionals = $query->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'other_professional' => $otherProfessionals->items(),
            'pagination' => [
                'current_page' => $otherProfessionals->currentPage(),
                'per_page' => $otherProfessionals->perPage(),
                'total' => $otherProfessionals->total(),
                'last_page' => $otherProfessionals->lastPage(),
                'from' => $otherProfessionals->firstItem(),
                'to' => $otherProfessionals->lastItem()
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreOtherProfessionalRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            $otherProfessional = OtherProfessional::create($validatedData);
            $otherProfessional->save();

            return response()->json([
                'success'=>'registered as an other professional'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOtherProfessionalRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $otherProfessional = OtherProfessional::with('user')->findOrFail($id);
        return response()->json([
            'other_professional'=>$otherProfessional
        ]);
    }

    /**
     * Get other professional by user_id
     */
    public function getOtherProfessionalUser(string $id)
    {
        $otherProfessional = OtherProfessional::with('user')->where('user_id', $id)->first();
        return response()->json([
            'other_professional'=>$otherProfessional
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
    public function update(UpdateOtherProfessionalRequest $request, string $id)
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
