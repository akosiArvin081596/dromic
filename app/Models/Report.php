<?php

namespace App\Models;

use App\Enums\ReportType;
use App\Observers\ReportObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ReportObserver::class)]
class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;

    protected $attributes = [
        'non_idps' => '[]',
        'related_incidents' => '[]',
        'casualties_injured' => '[]',
        'casualties_missing' => '[]',
        'casualties_dead' => '[]',
        'infrastructure_damages' => '[]',
        'agriculture_damages' => '[]',
        'assistance_provided' => '[]',
        'class_suspensions' => '[]',
        'work_suspensions' => '[]',
        'lifelines_roads_bridges' => '[]',
        'lifelines_power' => '[]',
        'lifelines_water' => '[]',
        'lifelines_communication' => '[]',
        'seaport_status' => '[]',
        'airport_status' => '[]',
        'landport_status' => '[]',
        'stranded_passengers' => '[]',
        'calamity_declarations' => '[]',
        'preemptive_evacuations' => '[]',
        'gaps_challenges' => '[]',
    ];

    protected $fillable = [
        'user_id',
        'incident_id',
        'report_number',
        'report_type',
        'sequence_number',
        'previous_report_id',
        'city_municipality_id',
        'report_date',
        'report_time',
        'situation_overview',
        'affected_areas',
        'inside_evacuation_centers',
        'age_distribution',
        'vulnerable_sectors',
        'outside_evacuation_centers',
        'non_idps',
        'damaged_houses',
        'related_incidents',
        'casualties_injured',
        'casualties_missing',
        'casualties_dead',
        'infrastructure_damages',
        'agriculture_damages',
        'assistance_provided',
        'class_suspensions',
        'work_suspensions',
        'lifelines_roads_bridges',
        'lifelines_power',
        'lifelines_water',
        'lifelines_communication',
        'seaport_status',
        'airport_status',
        'landport_status',
        'stranded_passengers',
        'calamity_declarations',
        'preemptive_evacuations',
        'gaps_challenges',
        'response_actions',
        'status',
        'return_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'report_type' => ReportType::class,
            'affected_areas' => 'array',
            'inside_evacuation_centers' => 'array',
            'age_distribution' => 'array',
            'vulnerable_sectors' => 'array',
            'outside_evacuation_centers' => 'array',
            'non_idps' => 'array',
            'damaged_houses' => 'array',
            'related_incidents' => 'array',
            'casualties_injured' => 'array',
            'casualties_missing' => 'array',
            'casualties_dead' => 'array',
            'infrastructure_damages' => 'array',
            'agriculture_damages' => 'array',
            'assistance_provided' => 'array',
            'class_suspensions' => 'array',
            'work_suspensions' => 'array',
            'lifelines_roads_bridges' => 'array',
            'lifelines_power' => 'array',
            'lifelines_water' => 'array',
            'lifelines_communication' => 'array',
            'seaport_status' => 'array',
            'airport_status' => 'array',
            'landport_status' => 'array',
            'stranded_passengers' => 'array',
            'calamity_declarations' => 'array',
            'preemptive_evacuations' => 'array',
            'gaps_challenges' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** @return BelongsTo<CityMunicipality, $this> */
    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(CityMunicipality::class);
    }

    /** @return BelongsTo<Report, $this> */
    public function previousReport(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_report_id');
    }

    /** @return HasOne<Report, $this> */
    public function nextReport(): HasOne
    {
        return $this->hasOne(self::class, 'previous_report_id');
    }

    /** @param Builder<Report> $query */
    public function scopeForIncidentAndLgu(Builder $query, int $incidentId, int $cityMunicipalityId): void
    {
        $query->where('incident_id', $incidentId)->where('city_municipality_id', $cityMunicipalityId);
    }

    /** @param Builder<Report> $query */
    public function scopeForCityMunicipality(Builder $query, int $cityMunicipalityId): void
    {
        $query->where('city_municipality_id', $cityMunicipalityId);
    }

    /** @param Builder<Report> $query */
    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }
}
