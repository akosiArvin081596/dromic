<?php

namespace App\Enums;

enum AugmentationType: string
{
    case FamilyFoodPacks = 'family_food_packs';
    case HygieneKits = 'hygiene_kits';
    case SleepingKits = 'sleeping_kits';
    case WaterContainers = 'water_containers';
    case KitchenUtensils = 'kitchen_utensils';

    public function label(): string
    {
        return match ($this) {
            self::FamilyFoodPacks => 'Family Food Packs',
            self::HygieneKits => 'Hygiene Kits',
            self::SleepingKits => 'Sleeping Kits',
            self::WaterContainers => 'Water Containers',
            self::KitchenUtensils => 'Kitchen Utensils',
        };
    }
}
