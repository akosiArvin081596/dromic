<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'type',
        'created_by',
        'description',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<CityMunicipality, $this> */
    public function cityMunicipalities(): BelongsToMany
    {
        return $this->belongsToMany(CityMunicipality::class, 'incident_city_municipality')->withTimestamps();
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** @return HasMany<RequestLetter, $this> */
    public function requestLetters(): HasMany
    {
        return $this->hasMany(RequestLetter::class);
    }

    public function computeDisplayName(): string
    {
        $reports = $this->reports()->with('cityMunicipality.province.region')->get();

        if ($reports->isEmpty()) {
            return $this->name;
        }

        $barangays = collect();
        foreach ($reports as $report) {
            foreach ($report->affected_areas ?? [] as $area) {
                $name = trim($area['barangay'] ?? '');
                if ($name !== '') {
                    $barangays->push($name);
                }
            }
        }

        $uniqueBarangays = $barangays->unique()->values();

        if ($uniqueBarangays->isEmpty()) {
            return $this->name;
        }

        $municipalities = $reports->pluck('cityMunicipality')->filter()->unique('id');
        $provinces = $municipalities->pluck('province')->filter()->unique('id');

        if ($municipalities->count() === 1) {
            $municipalityName = $municipalities->first()->name;

            if ($uniqueBarangays->count() === 1) {
                $brgy = $uniqueBarangays->first();
                if (! str_starts_with($brgy, 'Brgy.')) {
                    $brgy = "Brgy. {$brgy}";
                }

                return "{$this->name} at {$brgy}, {$municipalityName}";
            }

            return "{$this->name} at {$municipalityName}";
        }

        if ($provinces->count() === 1) {
            return "{$this->name} affecting {$provinces->first()->name}";
        }

        $regions = $provinces->pluck('region')->filter()->unique('id');

        if ($regions->isNotEmpty()) {
            return "{$this->name} affecting {$regions->first()->name}";
        }

        return $this->name;
    }

    public function refreshDisplayName(): void
    {
        $this->update(['display_name' => $this->computeDisplayName()]);
    }

    /** @param Builder<Incident> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', IncidentStatus::Active);
    }

    /** @param Builder<Incident> $query */
    public function scopeForCityMunicipality(Builder $query, int $cityMunicipalityId): void
    {
        $query->whereHas('cityMunicipalities', function (Builder $q) use ($cityMunicipalityId) {
            $q->where('city_municipalities.id', $cityMunicipalityId);
        });
    }
}
