<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\OtherProfessional;
use App\Support\ProfessionalListingQuery;
use App\Support\ProfessionalRegistration;

class ProfessionalController extends Controller
{
    /**
     * Unified healthcare staff listing with search and filters.
     */
    public function index()
    {
        $params = ProfessionalListingQuery::getListingParams();
        $type = $params['type'];
        $availability = $params['availability'];
        $perPage = max(1, min($params['per_page'], 500));
        $page = max(1, $params['page']);

        $sections = [];
        $summaryTotal = 0;
        $summaryAvailable = 0;
        $summaryUnavailable = 0;

        if (empty($type) || $type === 'doctor') {
            $doctorQuery = Doctor::with('user');
            ProfessionalListingQuery::applyCommonFilters($doctorQuery, $params);
            ProfessionalListingQuery::applyDoctorAvailabilityFilter($doctorQuery, $availability);

            $doctorPaginator = $doctorQuery->paginate($perPage, ['*'], 'page', $page);
            $doctorItems = collect($doctorPaginator->items())->map(function (Doctor $doctor) {
                return ProfessionalRegistration::appendFlagToDoctor($doctor);
            })->all();

            $availableCount = collect($doctorItems)->filter(fn ($d) => $d->availability === '1')->count();
            $unavailableCount = collect($doctorItems)->filter(fn ($d) => $d->availability === '0')->count();

            $sections['doctors'] = [
                'items' => $doctorItems,
                'total' => $doctorPaginator->total(),
                'available' => $availableCount,
                'unavailable' => $unavailableCount,
            ];

            $summaryTotal += $doctorPaginator->total();
            $summaryAvailable += $availableCount;
            $summaryUnavailable += $unavailableCount;
        }

        $includeNonDoctors = ProfessionalListingQuery::shouldIncludeNonDoctorSections($availability);

        if ($includeNonDoctors && (empty($type) || $type === 'nurse')) {
            $nurseQuery = Nurse::with('user');
            ProfessionalListingQuery::applyCommonFilters($nurseQuery, $params);

            $nursePaginator = $nurseQuery->paginate($perPage, ['*'], 'page', $page);
            $nurseItems = collect($nursePaginator->items())->map(function (Nurse $nurse) {
                return ProfessionalRegistration::appendFlagToNurse($nurse);
            })->all();

            $sections['nurses'] = [
                'items' => $nurseItems,
                'total' => $nursePaginator->total(),
            ];

            $summaryTotal += $nursePaginator->total();
            $summaryAvailable += $nursePaginator->total();
        }

        if ($includeNonDoctors && (empty($type) || $type === 'other_professional')) {
            $otherQuery = OtherProfessional::with('user');
            ProfessionalListingQuery::applyCommonFilters($otherQuery, array_merge($params, [
                'extra_search_fields' => ['professional_type'],
            ]));

            $otherPaginator = $otherQuery->paginate($perPage, ['*'], 'page', $page);
            $otherItems = collect($otherPaginator->items())->map(function (OtherProfessional $op) {
                return ProfessionalRegistration::appendFlagToOtherProfessional($op);
            })->all();

            $sections['other_professionals'] = [
                'items' => $otherItems,
                'total' => $otherPaginator->total(),
            ];

            $summaryTotal += $otherPaginator->total();
            $summaryAvailable += $otherPaginator->total();
        }

        return response()->json([
            'sections' => empty($sections) ? (object) [] : $sections,
            'summary' => [
                'total' => $summaryTotal,
                'available' => $summaryAvailable,
                'unavailable' => $summaryUnavailable,
            ],
        ]);
    }
}
