<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\OtherProfessional;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Support\ShareCardRenderer;
use App\Support\ProfessionalListingQuery;
use App\Support\ProfessionalRegistration;

class ProfessionalController extends Controller
{
    /**
     * Public, shareable professional profile by vanity slug (phoenix.com/<slug>).
     * No auth. Returns only public-safe fields — never email, phone, license or bank details.
     */
    /**
     * Link-preview image for the shareable profile URL: the professional's photo
     * blurred and washed in Phoenix navy, with a crisp round portrait on top.
     */
    public function shareImage(string $slug)
    {
        $user = User::where('slug', $slug)
            ->whereIn('user_type', ['doctor', 'nurse', 'other_professional'])
            ->first();
        if (!$user) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $w = 1200; $h = 630;
        $canvas = imagecreatetruecolor($w, $h);
        $navy = [23, 34, 77];

        $photo = null;
        $path = $user->passport ? 'public/files/' . basename($user->passport) : null;
        if ($path && Storage::disk('local')->exists($path)) {
            $photo = @imagecreatefromstring(Storage::disk('local')->get($path));
        }

        if ($photo) {
            $pw = imagesx($photo); $ph = imagesy($photo);

            // Background: cover-crop to 1200x630, blurred via a small copy scaled back up.
            $scale = max($w / $pw, $h / $ph);
            $cw = (int) ceil($w / $scale); $ch = (int) ceil($h / $scale);
            $cx = (int) (($pw - $cw) / 2); $cy = (int) max(0, min($ph - $ch, ($ph - $ch) * 0.3));
            $small = imagecreatetruecolor(120, 63);
            imagecopyresampled($small, $photo, 0, 0, $cx, $cy, 120, 63, $cw, $ch);
            for ($i = 0; $i < 12; $i++) {
                imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
            }
            // Two-step upscale with a blur between, so the enlarged blur has no visible blocks.
            $mid = imagecreatetruecolor(600, 315);
            imagecopyresampled($mid, $small, 0, 0, 0, 0, 600, 315, 120, 63);
            for ($i = 0; $i < 8; $i++) {
                imagefilter($mid, IMG_FILTER_GAUSSIAN_BLUR);
            }
            imagecopyresampled($canvas, $mid, 0, 0, 0, 0, $w, $h, 600, 315);
            imagedestroy($small);
            imagedestroy($mid);

            // Navy wash
            $wash = imagecolorallocatealpha($canvas, $navy[0], $navy[1], $navy[2], 38);
            imagefilledrectangle($canvas, 0, 0, $w, $h, $wash);

            // Crisp circular portrait with a white ring
            $d = 300; $side = min($pw, $ph);
            $portrait = imagecreatetruecolor($d, $d);
            imagecopyresampled($portrait, $photo, 0, 0, (int) (($pw - $side) / 2), (int) (($ph - $side) / 2), $d, $d, $side, $side);
            $ox = (int) (($w - $d) / 2); $oy = (int) (($h - $d) / 2);
            imagefilledellipse($canvas, $ox + $d / 2, $oy + $d / 2, $d + 16, $d + 16, imagecolorallocate($canvas, 255, 255, 255));
            $r = $d / 2;
            for ($y = 0; $y < $d; $y++) {
                for ($x = 0; $x < $d; $x++) {
                    if (($x - $r) ** 2 + ($y - $r) ** 2 <= $r * $r) {
                        imagesetpixel($canvas, $ox + $x, $oy + $y, imagecolorat($portrait, $x, $y));
                    }
                }
            }
            imagedestroy($portrait);
            imagedestroy($photo);
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $navy[0], $navy[1], $navy[2]));
        }

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Downloadable, shareable profile card (portrait 1080x1350) — the "navy hero"
     * design with photo, name, specialty and a QR code to the public profile.
     * No fee/pricing on the card: it's an identity card, not a quote, and a
     * shared image would go stale if the fee changed later.
     * Public and keyed by slug, so a visitor shares the same card as the owner.
     */
    public function card(string $slug)
    {
        $user = User::where('slug', $slug)
            ->whereIn('user_type', ['doctor', 'nurse', 'other_professional'])
            ->first();
        if (!$user) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $pro = match ($user->user_type) {
            'doctor' => Doctor::with('hospital')->where('user_id', $user->id)->first(),
            'nurse'  => Nurse::with('hospital')->where('user_id', $user->id)->first(),
            default  => OtherProfessional::with('hospital')->where('user_id', $user->id)->first(),
        };
        if (!$pro) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $subtitle = $user->user_type === 'other_professional'
            ? ($pro->professional_type ?? $pro->specialization ?? 'Specialist')
            : ($pro->specialization ?? ucfirst($user->user_type));

        $photoPath = $user->passport ? 'public/files/' . basename($user->passport) : null;
        $photo = ($photoPath && Storage::disk('local')->exists($photoPath))
            ? Storage::disk('local')->get($photoPath)
            : null;

        $png = ShareCardRenderer::render([
            'name'      => $user->name,
            'subtitle'  => $subtitle,
            'hospital'  => $pro->hospital ? $pro->hospital->name : null,
            'verified'  => (bool) \App\Support\ProfessionalRegistration::registrationComplete($user),
            'slug'      => $user->slug,
            'photo'     => $photo,
        ]);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="phoenix-' . $user->slug . '.png"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function publicProfile(string $slug)
    {
        $user = User::where('slug', $slug)
            ->whereIn('user_type', ['doctor', 'nurse', 'other_professional'])
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $pro = null;
        if ($user->user_type === 'doctor') {
            $pro = Doctor::with(['hospital', 'bankAccount'])->where('user_id', $user->id)->first();
        } elseif ($user->user_type === 'nurse') {
            $pro = Nurse::with(['hospital', 'bankAccount'])->where('user_id', $user->id)->first();
        } else {
            $pro = OtherProfessional::with(['hospital', 'bankAccount'])->where('user_id', $user->id)->first();
        }

        if (!$pro) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $fee = $pro->bankAccount ? (float) $pro->bankAccount->consultation_fee : null;

        return response()->json([
            'profile' => [
                'id' => $pro->id,
                'user_id' => $user->id,
                'slug' => $user->slug,
                'name' => $user->name,
                'gender' => $user->gender,
                'passport' => $user->passport,
                'user_type' => $user->user_type,
                'display_type' => $user->user_type === 'other_professional'
                    ? ($pro->professional_type ?? 'Specialist')
                    : ucfirst($user->user_type),
                'specialization' => $pro->specialization ?? null,
                'professional_type' => $pro->professional_type ?? null,
                'med_school' => $pro->med_school ?? null,
                'grad_year' => $pro->grad_year ?? null,
                'years_experience' => $pro->grad_year ? max(0, (int) date('Y') - (int) $pro->grad_year) : null,
                'availability' => $pro->availability ?? null,
                'hospital' => $pro->hospital ? $pro->hospital->name : null,
                'consultation_fee' => $fee,
                'verified' => (bool) \App\Support\ProfessionalRegistration::registrationComplete($user),
                'member_since' => optional($user->created_at)->format('Y'),
            ],
        ]);
    }

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
            $doctorQuery = Doctor::with('user')->whereHas('user'); // exclude deactivated (soft-deleted) users
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
            $nurseQuery = Nurse::with('user')->whereHas('user'); // exclude deactivated users
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
            $otherQuery = OtherProfessional::with('user')->whereHas('user'); // exclude deactivated users
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
