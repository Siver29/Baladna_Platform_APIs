<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the report categories and link each to a responsible agency.
     */
    public function run(): void
    {
        $municipality = Agency::where('name', 'Municipality')->first();
        $water = Agency::where('name', 'Water Department')->first();
        $electricity = Agency::where('name', 'Electricity Department')->first();
        $roads = Agency::where('name', 'Road Maintenance Department')->first();

        Category::create([
            'name' => 'Waste accumulation',
            'description' => 'Garbage or waste not collected.',
            'agency_id' => $municipality->id,
        ]);

        Category::create([
            'name' => 'Water leak',
            'description' => 'Leaking pipes or water on the street.',
            'agency_id' => $water->id,
        ]);

        Category::create([
            'name' => 'Broken streetlight',
            'description' => 'Streetlight not working.',
            'agency_id' => $electricity->id,
        ]);

        Category::create([
            'name' => 'Damaged road',
            'description' => 'Potholes and damaged road surfaces.',
            'agency_id' => $roads->id,
        ]);
    }
}
