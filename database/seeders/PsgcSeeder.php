<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PsgcSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $region = $this->seedRegion();
            $this->seedProvincesAndChildren($region);
        });

        $this->command->table(
            ['Table', 'Count'],
            [
                ['Regions', Region::count()],
                ['Provinces', Province::count()],
                ['City/Municipalities', CityMunicipality::count()],
                ['Barangays', Barangay::count()],
            ]
        );
    }

    private function seedRegion(): Region
    {
        return Region::updateOrCreate(
            ['psgc_code' => '1600000000'],
            ['name' => 'Caraga']
        );
    }

    private function seedProvincesAndChildren(Region $region): void
    {
        foreach ($this->getData() as $provinceData) {
            $province = Province::updateOrCreate(
                ['psgc_code' => $provinceData['code']],
                ['name' => $provinceData['name'], 'region_id' => $region->id]
            );

            $this->command->info("Province: {$province->name}");

            foreach ($provinceData['cities'] as $cityData) {
                $city = CityMunicipality::updateOrCreate(
                    ['psgc_code' => $cityData['code']],
                    ['name' => $cityData['name'], 'province_id' => $province->id]
                );

                $barangays = collect($cityData['barangays'])->map(fn (array $b) => [
                    'psgc_code' => $b['code'],
                    'name' => $b['name'],
                    'city_municipality_id' => $city->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                Barangay::upsert($barangays, ['psgc_code'], ['name', 'city_municipality_id', 'updated_at']);

                $this->command->info("  {$city->name}: " . count($barangays) . ' barangays');
            }
        }
    }

    /** @return array<int, array{code: string, name: string, cities: array<int, array{code: string, name: string, barangays: array<int, array{code: string, name: string}>}>}> */
    private function getData(): array
    {
        return [
            $this->agusan_del_norte(),
            $this->agusan_del_sur(),
            $this->surigao_del_norte(),
            $this->surigao_del_sur(),
            $this->dinagat_islands(),
        ];
    }

    private function agusan_del_norte(): array
    {
        return [
            'code' => '1600200000',
            'name' => 'Agusan del Norte',
            'cities' => json_decode(file_get_contents(database_path('data/agusan_del_norte.json')), true),
        ];
    }

    private function agusan_del_sur(): array
    {
        return [
            'code' => '1600300000',
            'name' => 'Agusan del Sur',
            'cities' => json_decode(file_get_contents(database_path('data/agusan_del_sur.json')), true),
        ];
    }

    private function surigao_del_norte(): array
    {
        return [
            'code' => '1606700000',
            'name' => 'Surigao del Norte',
            'cities' => json_decode(file_get_contents(database_path('data/surigao_del_norte.json')), true),
        ];
    }

    private function surigao_del_sur(): array
    {
        return [
            'code' => '1606800000',
            'name' => 'Surigao del Sur',
            'cities' => json_decode(file_get_contents(database_path('data/surigao_del_sur.json')), true),
        ];
    }

    private function dinagat_islands(): array
    {
        return [
            'code' => '1608500000',
            'name' => 'Dinagat Islands',
            'cities' => json_decode(file_get_contents(database_path('data/dinagat_islands.json')), true),
        ];
    }
}
