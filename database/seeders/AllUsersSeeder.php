<?php

namespace Database\Seeders;

use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AllUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $region = Region::first();

        // Admin
        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin User',
            'password' => $password,
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);

        // Regional
        if ($region) {
            User::firstOrCreate(['email' => 'regional@dswd.gov.ph'], [
                'name' => 'Regional User',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => 'regional',
                'region_id' => $region->id,
            ]);

            // Regional Director
            User::firstOrCreate(['email' => 'rd@dromic.test'], [
                'name' => 'Regional Director',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => 'regional_director',
                'region_id' => $region->id,
            ]);
        }

        // Provincial users — one per province
        Province::all()->each(function (Province $province) use ($password) {
            $slug = Str::slug($province->name);
            User::firstOrCreate(['email' => "{$slug}@dswd.gov.ph"], [
                'name' => $province->name . ' Provincial User',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => 'provincial',
                'province_id' => $province->id,
            ]);
        });

        // LGU users — one per city/municipality
        CityMunicipality::with('province')->get()->each(function (CityMunicipality $city) use ($password) {
            $slug = Str::slug($city->name);

            // Handle duplicate city names across provinces
            $email = "{$slug}@{$slug}.gov.ph";
            if (User::where('email', $email)->exists()) {
                $provinceSuffix = Str::slug($city->province->name ?? 'unknown');
                $email = "{$slug}-{$provinceSuffix}@{$slug}.gov.ph";
            }

            User::firstOrCreate(['email' => $email], [
                'name' => $city->name . ' LGU User',
                'password' => $password,
                'email_verified_at' => now(),
                'role' => 'lgu',
                'city_municipality_id' => $city->id,
            ]);
        });

        $this->command->info('Created ' . User::count() . ' total users.');
    }
}
