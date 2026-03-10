<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\User;

class IncidentNotificationService
{
    /**
     * Get all user IDs that should be notified about an incident affecting the given city/municipalities.
     *
     * Regional users are excluded — they only receive notifications when reports are validated.
     *
     * @param  list<int>  $cityMunicipalityIds
     * @return list<int>
     */
    public function getRecipientUserIds(array $cityMunicipalityIds, int $excludeUserId): array
    {
        $provinceIds = CityMunicipality::query()
            ->whereIn('id', $cityMunicipalityIds)
            ->pluck('province_id')
            ->unique()
            ->values()
            ->all();

        $regionIds = Province::query()
            ->whereIn('id', $provinceIds)
            ->pluck('region_id')
            ->unique()
            ->values()
            ->all();

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($cityMunicipalityIds, $provinceIds) {
                $query->where('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($provinceIds) {
                        $q->where('role', UserRole::Provincial)
                            ->whereIn('province_id', $provinceIds);
                    })
                    ->orWhere(function ($q) use ($cityMunicipalityIds) {
                        $q->where('role', UserRole::Lgu)
                            ->whereIn('city_municipality_id', $cityMunicipalityIds);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Get all user IDs within a region that should be notified about a massive incident.
     *
     * Regional users are excluded — they only receive notifications when reports are validated.
     *
     * @return list<int>
     */
    public function getRegionRecipientUserIds(int $regionId, int $excludeUserId): array
    {
        $provinceIds = Province::query()
            ->where('region_id', $regionId)
            ->pluck('id')
            ->all();

        $cityMunicipalityIds = CityMunicipality::query()
            ->whereIn('province_id', $provinceIds)
            ->pluck('id')
            ->all();

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($provinceIds, $cityMunicipalityIds) {
                $query->where('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($provinceIds) {
                        $q->where('role', UserRole::Provincial)
                            ->whereIn('province_id', $provinceIds);
                    })
                    ->orWhere(function ($q) use ($cityMunicipalityIds) {
                        $q->where('role', UserRole::Lgu)
                            ->whereIn('city_municipality_id', $cityMunicipalityIds);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Get user IDs that should be notified when a request letter is submitted.
     *
     * Returns Admin users and Provincial users in the same province.
     * Regional users are not notified — they receive notifications only after Provincial endorsement.
     *
     * @return list<int>
     */
    public function getRequestLetterRecipientUserIds(int $cityMunicipalityId, int $excludeUserId): array
    {
        $provinceId = CityMunicipality::query()
            ->where('id', $cityMunicipalityId)
            ->value('province_id');

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($provinceId) {
                $query->where('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($provinceId) {
                        $q->where('role', UserRole::Provincial)
                            ->where('province_id', $provinceId);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Get user IDs that should be notified when a request letter is endorsed.
     *
     * Returns the LGU who submitted, Admin users, and Regional users in the same region.
     *
     * @return list<int>
     */
    public function getEndorsementRecipientUserIds(int $cityMunicipalityId, int $requestLetterUserId, int $excludeUserId): array
    {
        $provinceId = CityMunicipality::query()
            ->where('id', $cityMunicipalityId)
            ->value('province_id');

        $regionId = Province::query()
            ->where('id', $provinceId)
            ->value('region_id');

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($requestLetterUserId, $regionId) {
                $query->where('id', $requestLetterUserId)
                    ->orWhere('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($regionId) {
                        $q->where('role', UserRole::Regional)
                            ->where('region_id', $regionId);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Get user IDs that should be notified when a request letter is approved.
     *
     * Returns the LGU who submitted, Provincial users in the same province, Regional users in the same region, and Admin users.
     *
     * @return list<int>
     */
    public function getApprovalRecipientUserIds(int $cityMunicipalityId, int $requestLetterUserId, int $excludeUserId): array
    {
        $provinceId = CityMunicipality::query()
            ->where('id', $cityMunicipalityId)
            ->value('province_id');

        $regionId = Province::query()
            ->where('id', $provinceId)
            ->value('region_id');

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($requestLetterUserId, $provinceId, $regionId) {
                $query->where('id', $requestLetterUserId)
                    ->orWhere('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($regionId) {
                        $q->where('role', UserRole::Regional)
                            ->where('region_id', $regionId);
                    })
                    ->orWhere(function ($q) use ($provinceId) {
                        $q->where('role', UserRole::Provincial)
                            ->where('province_id', $provinceId);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Get user IDs that should be notified when a delivery is recorded.
     *
     * Returns the LGU who submitted the request letter, Provincial users in the same province,
     * Regional users in the same region, and Admin users.
     *
     * @return list<int>
     */
    public function getDeliveryRecipientUserIds(int $cityMunicipalityId, int $requestLetterUserId, int $excludeUserId): array
    {
        $provinceId = CityMunicipality::query()
            ->where('id', $cityMunicipalityId)
            ->value('province_id');

        $regionId = Province::query()
            ->where('id', $provinceId)
            ->value('region_id');

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($requestLetterUserId, $provinceId, $regionId) {
                $query->where('id', $requestLetterUserId)
                    ->orWhere('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($regionId) {
                        $q->where('role', UserRole::Regional)
                            ->where('region_id', $regionId);
                    })
                    ->orWhere(function ($q) use ($provinceId) {
                        $q->where('role', UserRole::Provincial)
                            ->where('province_id', $provinceId);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Get user IDs that should be notified when a report is validated or returned.
     *
     * Returns the LGU report creator, Admin users, and Regional users in the same region.
     *
     * @return list<int>
     */
    public function getValidationRecipientUserIds(int $cityMunicipalityId, int $reportUserId, int $excludeUserId): array
    {
        $provinceId = CityMunicipality::query()
            ->where('id', $cityMunicipalityId)
            ->value('province_id');

        $regionId = Province::query()
            ->where('id', $provinceId)
            ->value('region_id');

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($reportUserId, $regionId) {
                $query->where('id', $reportUserId)
                    ->orWhere('role', UserRole::Admin)
                    ->orWhere(function ($q) use ($regionId) {
                        $q->where('role', UserRole::Regional)
                            ->where('region_id', $regionId);
                    });
            })
            ->pluck('id')
            ->all();
    }
}
