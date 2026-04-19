<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Http\Requests\StoreNurseRequest;
use App\Http\Requests\UpdateNurseRequest;
use App\Support\ProfessionalRegistration;

class NurseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Nurse::with('user');

        if (request()->has('search') && !empty(request('search'))) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phoneno', 'like', "%{$search}%");
                })->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%");
            });
        }

        $perPage = request('per_page', 10);
        $page = request('page', 1);

        $nurses = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($nurses->items())->map(function (Nurse $nurse) {
            return ProfessionalRegistration::appendFlagToNurse($nurse);
        })->all();

        return response()->json([
            'nurse' => $items,
            'pagination' => [
                'current_page' => $nurses->currentPage(),
                'per_page' => $nurses->perPage(),
                'total' => $nurses->total(),
                'last_page' => $nurses->lastPage(),
                'from' => $nurses->firstItem(),
                'to' => $nurses->lastItem(),
            ],
        ]);
    }

    public function create(StoreNurseRequest $request)
    {
        $validatedData = $request->validated();

        if (Nurse::where('user_id', $validatedData['user_id'])->exists()) {
            return response()->json([
                'error' => 'A professional profile already exists for this account. Use update to complete your registration.',
                'error_code' => 'DUPLICATE_PROFESSIONAL_PROFILE',
            ], 409);
        }

        $nurse = Nurse::create($validatedData);
        $nurse->save();
        $nurse->load('user');
        ProfessionalRegistration::appendFlagToNurse($nurse);

        return response()->json([
            'success' => 'registered as a nurse',
            'nurse' => $nurse,
        ]);
    }

    public function store(StoreNurseRequest $request)
    {
        //
    }

    public function show(Nurse $nurse, $id)
    {
        $nurse = Nurse::with('user')->findOrFail($id);
        ProfessionalRegistration::appendFlagToNurse($nurse);

        return response()->json([
            'nurse' => $nurse,
        ]);
    }

    public function edit(Nurse $nurse)
    {
        //
    }

    public function updateProfile(UpdateNurseRequest $request, string $id)
    {
        $nurse = Nurse::with('user')->findOrFail($id);
        $nurse->fill($request->validated());
        $nurse->save();
        $nurse->load('user');
        ProfessionalRegistration::appendFlagToNurse($nurse);

        return response()->json([
            'success' => true,
            'nurse' => $nurse,
        ]);
    }

    public function destroy(Nurse $nurse)
    {
        //
    }

    /**
     * Nurse profile by user id (for SPA).
     */
    public function getNurseByUser(string $userId)
    {
        $nurse = Nurse::with('user')->where('user_id', $userId)->first();
        if ($nurse) {
            ProfessionalRegistration::appendFlagToNurse($nurse);
        }

        return response()->json([
            'nurse' => $nurse,
        ]);
    }
}
