<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CityMunicipality extends Model
{
    /** @use HasFactory<\Database\Factories\CityMunicipalityFactory> */
    use HasFactory;

    protected $fillable = [
        'psgc_code',
        'name',
        'province_id',
    ];

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return HasMany<Barangay, $this> */
    public function barangays(): HasMany
    {
        return $this->hasMany(Barangay::class);
    }

    /** @return BelongsToMany<Incident, $this> */
    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class, 'incident_city_municipality')->withTimestamps();
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
