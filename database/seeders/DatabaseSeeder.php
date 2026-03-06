<?php

namespace Database\Seeders;

use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PsgcSeeder::class);

        $caragaRegion = Region::first();
        $firstProvince = Province::first();
        $firstCityMunicipality = CityMunicipality::first();
        $secondCityMunicipality = CityMunicipality::skip(1)->first();
        $thirdCityMunicipality = CityMunicipality::skip(2)->first();

        // Admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Regional user
        if ($caragaRegion) {
            User::factory()->regional($caragaRegion)->create([
                'name' => 'Regional User',
                'email' => 'regional@example.com',
            ]);
        }

        // Provincial user
        User::factory()->provincial($firstProvince)->create([
            'name' => 'Provincial User',
            'email' => 'provincial@example.com',
        ]);

        // LGU users
        User::factory()->lgu($firstCityMunicipality)->create([
            'name' => 'LGU User 1',
            'email' => 'lgu1@example.com',
        ]);

        if ($secondCityMunicipality) {
            User::factory()->lgu($secondCityMunicipality)->create([
                'name' => 'LGU User 2',
                'email' => 'lgu2@example.com',
            ]);
        }

        if ($thirdCityMunicipality) {
            User::factory()->lgu($thirdCityMunicipality)->create([
                'name' => 'LGU User 3',
                'email' => 'lgu3@example.com',
            ]);
        }

        // $this->call(IncidentSeeder::class);
    }
}
