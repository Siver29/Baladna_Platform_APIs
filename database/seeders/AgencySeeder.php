<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Seed the responsible agencies.
     */
    public function run(): void
    {
        Agency::create([
            'name' => 'Municipality',
            'description' => 'Responsible for municipal services and waste.',
        ]);

        Agency::create([
            'name' => 'Water Department',
            'description' => 'Responsible for water supply and leaks.',
        ]);

        Agency::create([
            'name' => 'Electricity Department',
            'description' => 'Responsible for electricity and streetlights.',
        ]);

        Agency::create([
            'name' => 'Road Maintenance Department',
            'description' => 'Responsible for roads and public infrastructure.',
        ]);
    }
}
