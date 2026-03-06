<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEscort()) {
            return $delivery->escort_user_id === $user->id;
        }

        $requestLetter = $delivery->requestLetter;

        if ($user->isRegional()) {
            return $requestLetter->cityMunicipality?->province?->region_id === $user->region_id;
        }

        if ($user->isProvincial()) {
            return $requestLetter->cityMunicipality?->province_id === $user->province_id;
        }

        return $requestLetter->city_municipality_id === $user->city_municipality_id;
    }

    public function update(User $user, Delivery $delivery): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEscort()) {
            return $delivery->escort_user_id === $user->id;
        }

        if ($user->isRros()) {
            $requestLetter = $delivery->requestLetter;

            return $requestLetter->cityMunicipality?->province?->region_id === $user->region_id;
        }

        return false;
    }
}
