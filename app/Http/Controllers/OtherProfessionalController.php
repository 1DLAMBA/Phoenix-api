<?php

namespace App\Http\Controllers;

use App\Models\OtherProfessional;
use App\Http\Requests\StoreOtherProfessionalRequest;
use App\Http\Requests\UpdateOtherProfessionalRequest;
use App\Support\ProfessionalRegistration;

class OtherProfessionalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = OtherProfessional::with('user');

        if (request()->has('search') && !empty(request('search'))) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phoneno', 'like', "%{$search}%");
                })->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhere('professional_type', 'like', "%{$search}%");
            });
        }

        $perPage = request('per_page', 10);
        $page = request('page', 1);

        $otherProfessionals = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($otherProfessionals->items())->map(function (OtherProfessional $op) {
            return ProfessionalRegistration::appendFlagToOtherProfessional($op);
        })->all();

        return response()->json([
            'other_professional' => $items,
            'pagination' => [
                'current_page' => $otherProfessionals->currentPage(),
                'per_page' => $otherProfessionals->perPage(),
                'total' => $otherProfessionals->total(),
                'last_page' => $otherProfessionals->lastPage(),
                'from' => $otherProfessionals->firstItem(),
                'to' => $otherProfessionals->lastItem(),
            ],
        ]);
    }

    public function create(StoreOtherProfessionalRequest $request)
    {
        $validatedData = $request->validated();

        if (OtherProfessional::where('user_id', $validatedData['user_id'])->exists()) {
            return response()->json([
                'error' => 'A professional profile already exists for this account. Use update to complete your registration.',
                'error_code' => 'DUPLICATE_PROFESSIONAL_PROFILE',
            ], 409);
        }

        $otherProfessional = OtherProfessional::create($validatedData);
        $otherProfessional->save();
        $otherProfessional->load('user');
        ProfessionalRegistration::appendFlagToOtherProfessional($otherProfessional);

        return response()->json([
            'success' => 'registered as an other professional',
            'other_professional' => $otherProfessional,
        ]);
    }

    public function store(StoreOtherProfessionalRequest $request)
    {
        //
    }

    public function show($id)
    {
        $otherProfessional = OtherProfessional::with('user')->findOrFail($id);
        ProfessionalRegistration::appendFlagToOtherProfessional($otherProfessional);

        return response()->json([
            'other_professional' => $otherProfessional,
        ]);
    }

    public function getOtherProfessionalUser(string $id)
    {
        $otherProfessional = OtherProfessional::with('user')->where('user_id', $id)->first();
        if ($otherProfessional) {
            ProfessionalRegistration::appendFlagToOtherProfessional($otherProfessional);
        }

        return response()->json([
            'other_professional' => $otherProfessional,
        ]);
    }

    public function edit(string $id)
    {
        //
    }

    public function updateProfile(UpdateOtherProfessionalRequest $request, string $id)
    {
        $otherProfessional = OtherProfessional::with('user')->findOrFail($id);
        $otherProfessional->fill($request->validated());
        $otherProfessional->save();
        $otherProfessional->load('user');
        ProfessionalRegistration::appendFlagToOtherProfessional($otherProfessional);

        return response()->json([
            'success' => true,
            'other_professional' => $otherProfessional,
        ]);
    }

    public function destroy(string $id)
    {
        //
    }
}
