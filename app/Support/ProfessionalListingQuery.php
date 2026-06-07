<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class ProfessionalListingQuery
{
    /**
     * @param  array<string, mixed>  $options
     */
    public static function applyCommonFilters(Builder $query, array $options = []): Builder
    {
        $search = $options['search'] ?? request('search');
        $verifiedOnly = self::boolParam($options['verified_only'] ?? request('verified_only', '1'));
        $excludeDemo = self::boolParam($options['exclude_demo'] ?? request('exclude_demo', '1'));
        $extraSearchFields = $options['extra_search_fields'] ?? [];

        if ($verifiedOnly) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereNotNull('email_verified_at');
            });
        }

        if ($excludeDemo) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereRaw('LOWER(name) NOT LIKE ?', ['%demo%']);
            });
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search, $extraSearchFields) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phoneno', 'like', "%{$search}%");
                })->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%");

                foreach ($extraSearchFields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    public static function applyDoctorAvailabilityFilter(Builder $query, ?string $availability): Builder
    {
        if (empty($availability)) {
            return $query;
        }

        if ($availability === 'available') {
            $query->where('availability', '1');
        } elseif ($availability === 'unavailable') {
            $query->where('availability', '0');
        }

        return $query;
    }

    public static function shouldIncludeNonDoctorSections(?string $availability): bool
    {
        return empty($availability) || $availability === 'available';
    }

    /**
     * @param  mixed  $value
     */
    public static function boolParam($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);

        return ! in_array($normalized, ['0', 'false', 'no', 'off'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getListingParams(): array
    {
        return [
            'search' => request('search'),
            'type' => request('type'),
            'availability' => request('availability'),
            'verified_only' => request('verified_only', '1'),
            'exclude_demo' => request('exclude_demo', '1'),
            'per_page' => (int) request('per_page', 100),
            'page' => (int) request('page', 1),
        ];
    }
}
