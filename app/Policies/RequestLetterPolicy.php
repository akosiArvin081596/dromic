<?php

namespace App\Policies;

use App\Enums\RequestLetterStatus;
use App\Models\Incident;
use App\Models\RequestLetter;
use App\Models\User;

class RequestLetterPolicy
{
    public function create(User $user, Incident $incident): bool
    {
        if (! $user->isLgu()) {
            return false;
        }

        // Massive incidents with no assigned LGUs — any LGU in the creator's region can upload
        if (! $incident->cityMunicipalities()->exists()) {
            if ($user->cityMunicipality?->province?->region_id !== $incident->creator?->region_id) {
                return false;
            }
        } elseif (! $incident->cityMunicipalities()->where('city_municipalities.id', $user->city_municipality_id)->exists()) {
            return false;
        }

        return $incident->reports()
            ->where('city_municipality_id', $user->city_municipality_id)
            ->exists();
    }

    public function view(User $user, RequestLetter $requestLetter): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isRegional()) {
            return $requestLetter->cityMunicipality?->province?->region_id === $user->region_id;
        }

        if ($user->isProvincial()) {
            return $requestLetter->cityMunicipality?->province_id === $user->province_id;
        }

        if ($user->isEscort()) {
            return $requestLetter->deliveries()->where('escort_user_id', $user->id)->exists();
        }

        return $requestLetter->city_municipality_id === $user->city_municipality_id;
    }

    public function delete(User $user, RequestLetter $requestLetter): bool
    {
        return $user->isLgu()
            && $requestLetter->user_id === $user->id
            && $requestLetter->status === RequestLetterStatus::Pending;
    }

    public function endorse(User $user, RequestLetter $requestLetter): bool
    {
        if (! $user->isProvincial()) {
            return false;
        }

        if ($requestLetter->status !== RequestLetterStatus::Pending) {
            return false;
        }

        return $requestLetter->cityMunicipality?->province_id === $user->province_id;
    }

    public function approve(User $user, RequestLetter $requestLetter): bool
    {
        if (! $user->isAdmin() && ! $user->isRros()) {
            return false;
        }

        if ($requestLetter->status !== RequestLetterStatus::Endorsed) {
            return false;
        }

        if ($user->isRros()) {
            return $requestLetter->cityMunicipality?->province?->region_id === $user->region_id;
        }

        return true;
    }

    public function recordDelivery(User $user, RequestLetter $requestLetter): bool
    {
        if (! $user->isAdmin() && ! $user->isRros()) {
            return false;
        }

        if (! in_array($requestLetter->status, [RequestLetterStatus::Approved, RequestLetterStatus::Delivering])) {
            return false;
        }

        if ($user->isRros()) {
            return $requestLetter->cityMunicipality?->province?->region_id === $user->region_id;
        }

        return true;
    }
}
