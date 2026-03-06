<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isRegionalDirector()) {
            return false;
        }

        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($user->isRegionalDirector()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($incident->created_by === $user->id) {
            return true;
        }

        // Massive incidents with no assigned LGUs — visible to everyone in the creator's region
        if (! $incident->cityMunicipalities()->exists()) {
            $creatorRegionId = $incident->creator?->region_id;

            if (! $creatorRegionId) {
                return false;
            }

            return $this->userBelongsToRegion($user, $creatorRegionId);
        }

        if ($user->isRegional()) {
            return $incident->cityMunicipalities()
                ->whereHas('province', fn ($q) => $q->where('region_id', $user->region_id))
                ->exists();
        }

        if ($user->isProvincial()) {
            return $incident->cityMunicipalities()
                ->whereHas('province', fn ($q) => $q->where('id', $user->province_id))
                ->exists();
        }

        return $incident->cityMunicipalities()
            ->where('city_municipalities.id', $user->city_municipality_id)
            ->exists();
    }

    private function userBelongsToRegion(User $user, int $regionId): bool
    {
        if ($user->isRegional()) {
            return $user->region_id === $regionId;
        }

        if ($user->isProvincial()) {
            return $user->province?->region_id === $regionId;
        }

        if ($user->isLgu()) {
            return $user->cityMunicipality?->province?->region_id === $regionId;
        }

        return false;
    }

    public function create(User $user): bool
    {
        if ($user->isRegionalDirector()) {
            return false;
        }

        return $user->isAdmin() || $user->isRegional() || $user->isLgu();
    }

    public function update(User $user, Incident $incident): bool
    {
        if ($user->isRegionalDirector()) {
            return false;
        }

        return $user->isAdmin() || $user->isRegional();
    }
}
