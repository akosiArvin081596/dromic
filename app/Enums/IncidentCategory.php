<?php

namespace App\Enums;

enum IncidentCategory: string
{
    case Fire = 'fire';
    case Flood = 'flood';
    case TropicalCyclone = 'tropical_cyclone';
    case Shearline = 'shearline';
    case Earthquake = 'earthquake';
    case Landslide = 'landslide';
    case StormSurge = 'storm_surge';
    case VolcanicActivity = 'volcanic_activity';
    case ArmedConflict = 'armed_conflict';
    case Drought = 'drought';
    case OilSpill = 'oil_spill';
    case TransportationIncident = 'transportation_incident';
    case BuildingCollapse = 'building_collapse';
    case DiseaseOutbreak = 'disease_outbreak';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fire => 'Fire',
            self::Flood => 'Flood',
            self::TropicalCyclone => 'Tropical Cyclone',
            self::Shearline => 'Shearline',
            self::Earthquake => 'Earthquake',
            self::Landslide => 'Landslide',
            self::StormSurge => 'Storm Surge',
            self::VolcanicActivity => 'Volcanic Activity',
            self::ArmedConflict => 'Armed Conflict',
            self::Drought => 'Drought',
            self::OilSpill => 'Oil Spill',
            self::TransportationIncident => 'Transportation Incident',
            self::BuildingCollapse => 'Building Collapse',
            self::DiseaseOutbreak => 'Disease Outbreak',
            self::Other => 'Other',
        };
    }
}
