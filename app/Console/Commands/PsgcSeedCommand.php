<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PsgcSeedCommand extends Command
{
    protected $signature = 'psgc:seed';

    protected $description = 'Seed geographic data from PSGC Cloud API for Caraga region';

    private const CARAGA_REGION_CODE = '1600000000';

    private const BASE_URL = 'https://psgc.cloud/api';

    public function handle(): int
    {
        $this->info('Fetching Caraga region geographic data from PSGC Cloud API...');

        try {
            DB::transaction(function () {
                $region = $this->seedRegion();
                $this->seedProvinces($region);
            });
        } catch (\Exception $e) {
            $this->error('Failed to seed geographic data: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Geographic data seeded successfully!');
        $this->table(
            ['Table', 'Count'],
            [
                ['Regions', Region::count()],
                ['Provinces', Province::count()],
                ['City/Municipalities', CityMunicipality::count()],
                ['Barangays', Barangay::count()],
            ]
        );

        return self::SUCCESS;
    }

    private function seedRegion(): Region
    {
        $regionData = $this->fetchApi('/regions/'.self::CARAGA_REGION_CODE);

        $region = Region::updateOrCreate(
            ['psgc_code' => $regionData['code']],
            ['name' => $regionData['name']]
        );

        $this->info("Region: {$region->name}");

        return $region;
    }

    private function seedProvinces(Region $region): void
    {
        $this->info('Fetching provinces...');

        $provinces = $this->fetchApi('/regions/'.self::CARAGA_REGION_CODE.'/provinces');

        foreach ($provinces as $provinceData) {
            $province = Province::updateOrCreate(
                ['psgc_code' => $provinceData['code']],
                ['name' => $provinceData['name'], 'region_id' => $region->id]
            );

            $this->info("  Province: {$province->name}");
            $this->seedCityMunicipalities($province);
        }
    }

    private function seedCityMunicipalities(Province $province): void
    {
        $cityMunicipalities = $this->fetchApi('/provinces/'.$province->psgc_code.'/cities-municipalities');

        foreach ($cityMunicipalities as $cmData) {
            $cm = CityMunicipality::updateOrCreate(
                ['psgc_code' => $cmData['code']],
                [
                    'name' => $cmData['name'],
                    'province_id' => $province->id,
                ]
            );

            $this->info("    City/Municipality: {$cm->name}");
            $this->seedBarangays($cm);
        }
    }

    private function seedBarangays(CityMunicipality $cityMunicipality): void
    {
        $barangays = $this->fetchApi('/cities-municipalities/'.$cityMunicipality->psgc_code.'/barangays');

        $barangayData = collect($barangays)->map(fn (array $brgy) => [
            'psgc_code' => $brgy['code'],
            'name' => $brgy['name'],
            'city_municipality_id' => $cityMunicipality->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        Barangay::upsert($barangayData, ['psgc_code'], ['name', 'city_municipality_id', 'updated_at']);

        $this->info("      Barangays: ".count($barangayData).' seeded');
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchApi(string $endpoint): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->retry(5, function (int $attempt) {
                $delay = $attempt * 2000;
                $this->warn("      Rate limited, waiting {$delay}ms before retry #{$attempt}...");

                return $delay;
            }, fn ($exception) => $exception->response?->status() === 429)
            ->get($endpoint);

        $response->throw();

        sleep(1);

        return $response->json();
    }
}
