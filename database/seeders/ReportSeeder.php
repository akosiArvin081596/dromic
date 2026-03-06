<?php

namespace Database\Seeders;

use App\Models\CityMunicipality;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $cityMunicipalities = CityMunicipality::inRandomOrder()->limit(5)->get();

        if ($cityMunicipalities->isEmpty()) {
            $this->command->warn('No city/municipalities found. Run php artisan psgc:seed first.');

            return;
        }

        foreach ($cityMunicipalities as $cm) {
            Report::factory()
                ->count(2)
                ->create([
                    'user_id' => $user->id,
                    'city_municipality_id' => $cm->id,
                ]);
        }

        $this->command->info('Created '.Report::count().' sample reports.');
    }
}
