<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $provinceFiles = [
        'Agusan del Norte' => 'agusan_del_norte.json',
        'Agusan del Sur' => 'agusan_del_sur.json',
        'Surigao del Norte' => 'surigao_del_norte.json',
        'Surigao del Sur' => 'surigao_del_sur.json',
        'Dinagat Islands' => 'dinagat_islands.json',
    ];

    /**
     * Cities whose names changed in the official PSGC API.
     *
     * @var array<string, string> old DB name => new API name
     */
    private array $renamedCities = [
        'Bayugan' => 'City of Bayugan',
        'Bislig' => 'City of Bislig',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $this->updateCityMunicipalityCodes();
            $this->reseedBarangays();
        });
    }

    public function down(): void
    {
        // Not reversible — run php artisan db:seed --class=PsgcSeeder to restore from JSON files
    }

    private function updateCityMunicipalityCodes(): void
    {
        // Collect all updates first, then apply in two phases to avoid unique constraint collisions
        $updates = [];

        foreach ($this->provinceFiles as $provinceName => $jsonFile) {
            $province = DB::table('provinces')->where('name', $provinceName)->first();

            if (! $province) {
                continue;
            }

            $cities = json_decode(file_get_contents(database_path("data/{$jsonFile}")), true);

            $dbCities = DB::table('city_municipalities')
                ->where('province_id', $province->id)
                ->get()
                ->keyBy('name');

            foreach ($cities as $city) {
                $dbCity = $dbCities->get($city['name']);

                // Handle renamed cities (e.g., "Bayugan" → "City of Bayugan")
                if (! $dbCity) {
                    $oldName = array_search($city['name'], $this->renamedCities);
                    if ($oldName !== false) {
                        $dbCity = $dbCities->get($oldName);
                    }
                }

                if (! $dbCity) {
                    continue;
                }

                $updates[] = [
                    'id' => $dbCity->id,
                    'psgc_code' => $city['code'],
                    'name' => $city['name'],
                ];
            }
        }

        // Phase 1: Set temporary codes to avoid unique constraint collisions
        foreach ($updates as $i => $update) {
            DB::table('city_municipalities')
                ->where('id', $update['id'])
                ->update(['psgc_code' => "TEMP_{$i}"]);
        }

        // Phase 2: Set correct codes and names
        foreach ($updates as $update) {
            DB::table('city_municipalities')
                ->where('id', $update['id'])
                ->update([
                    'psgc_code' => $update['psgc_code'],
                    'name' => $update['name'],
                    'updated_at' => now(),
                ]);
        }
    }

    private function reseedBarangays(): void
    {
        DB::table('barangays')->delete();

        $now = now();

        foreach ($this->provinceFiles as $provinceName => $jsonFile) {
            $province = DB::table('provinces')->where('name', $provinceName)->first();

            if (! $province) {
                continue;
            }

            $cities = json_decode(file_get_contents(database_path("data/{$jsonFile}")), true);

            $dbCities = DB::table('city_municipalities')
                ->where('province_id', $province->id)
                ->get()
                ->keyBy('psgc_code');

            foreach ($cities as $city) {
                $dbCity = $dbCities->get($city['code']);

                if (! $dbCity) {
                    continue;
                }

                $barangays = array_map(fn (array $b) => [
                    'psgc_code' => $b['code'],
                    'name' => $b['name'],
                    'city_municipality_id' => $dbCity->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $city['barangays']);

                // Insert in chunks to avoid SQLite variable limits
                foreach (array_chunk($barangays, 100) as $chunk) {
                    DB::table('barangays')->insert($chunk);
                }
            }
        }
    }
};
