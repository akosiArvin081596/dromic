<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user, Incident $incident): bool
    {
        if ($user->isRegionalDirector()) {
            return false;
        }

        return true;
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->isRegionalDirector()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isRegional()) {
            return $report->status === 'validated'
                && $report->cityMunicipality?->province?->region_id === $user->region_id;
        }

        if ($user->isProvincial()) {
            return $report->cityMunicipality?->province_id === $user->province_id;
        }

        return $report->city_municipality_id === $user->city_municipality_id;
    }

    public function create(User $user, Incident $incident): bool
    {
        if (! $user->isLgu()) {
            return false;
        }

        // Massive incidents with no assigned LGUs — any LGU in the creator's region can report
        if (! $incident->cityMunicipalities()->exists()) {
            return $user->cityMunicipality?->province?->region_id === $incident->creator?->region_id;
        }

        return $incident->cityMunicipalities()
            ->where('city_municipalities.id', $user->city_municipality_id)
            ->exists();
    }

    public function update(User $user, Report $report): bool
    {
        if ($user->isLgu() && $report->user_id === $user->id && in_array($report->status, ['draft', 'returned'])) {
            return true;
        }

        if ($user->isProvincial() && $report->status === 'for_validation') {
            return $report->cityMunicipality?->province_id === $user->province_id;
        }

        return false;
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->isLgu()
            && $report->user_id === $user->id
            && $report->status === 'draft';
    }

    public function validate(User $user, Report $report): bool
    {
        if (! $user->isProvincial()) {
            return false;
        }

        if ($report->status !== 'for_validation') {
            return false;
        }

        return $report->cityMunicipality?->province_id === $user->province_id;
    }

    public function returnReport(User $user, Report $report): bool
    {
        if (! $user->isProvincial()) {
            return false;
        }

        if ($report->status !== 'for_validation') {
            return false;
        }

        return $report->cityMunicipality?->province_id === $user->province_id;
    }

    public function viewConsolidated(User $user): bool
    {
        return $user->isAdmin() || $user->isRegional() || $user->isProvincial();
    }
}
