<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHospitalRequest;
use App\Models\Hospital;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    /**
     * Display a listing of approved hospitals (used to populate the
     * hospital picker on professional registration).
     */
    public function index()
    {
        $hospitals = Hospital::where('status', 'approved')->orderBy('name')->get();

        return response()->json([
            'hospital' => $hospitals,
        ]);
    }

    /**
     * Register a new hospital. Lands as 'pending' (the migration default)
     * until an admin approves it.
     */
    public function create(StoreHospitalRequest $request)
    {
        $hospital = Hospital::create($request->validated());
        $hospital->save();

        return response()->json([
            'Success' => 'Hospital registered, pending approval',
            'hospital' => $hospital,
        ]);
    }

    /**
     * Display the specified hospital.
     */
    public function show(Hospital $hospital, string $id)
    {
        $hospital = Hospital::findOrFail($id);

        return response()->json([
            'hospital' => $hospital,
        ]);
    }

    /**
     * Admin listing of hospitals across all statuses, optionally filtered.
     */
    public function adminIndex()
    {
        $query = Hospital::query();

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        $perPage = request('per_page', 10);
        $page = request('page', 1);

        $hospitals = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'hospital' => $hospitals->items(),
            'pagination' => [
                'current_page' => $hospitals->currentPage(),
                'per_page' => $hospitals->perPage(),
                'total' => $hospitals->total(),
                'last_page' => $hospitals->lastPage(),
            ],
        ]);
    }

    /**
     * Approve a pending hospital registration.
     */
    public function approve(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $hospital->update(['status' => 'approved']);

        return response()->json([
            'Success' => 'Hospital approved',
            'hospital' => $hospital,
        ]);
    }

    /**
     * Reject a pending hospital registration.
     */
    public function reject(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $hospital->update(['status' => 'rejected']);

        return response()->json([
            'Success' => 'Hospital rejected',
            'hospital' => $hospital,
        ]);
    }

    /**
     * The authenticated hospital's own profile.
     */
    public function me(Request $request)
    {
        $hospital = $request->user();

        return response()->json([
            'user' => array_merge($hospital->toArray(), ['user_type' => 'hospital']),
        ]);
    }

    /**
     * The authenticated hospital's registered staff.
     */
    public function staff(Request $request)
    {
        $hospital = $request->user()->load([
            'doctors.user',
            'nurses.user',
            'otherProfessionals.user',
        ]);

        return response()->json([
            'doctors' => $hospital->doctors,
            'nurses' => $hospital->nurses,
            'other_professionals' => $hospital->otherProfessionals,
        ]);
    }
}
