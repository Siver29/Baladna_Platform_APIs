<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Seed the areas with a simple hierarchy.
     */
    public function run(): void
    {
        $baghdad = Area::create(['name' => 'Baghdad']);

        Area::create(['name' => 'Karrada', 'parent_id' => $baghdad->id]);
        Area::create(['name' => 'Mansour', 'parent_id' => $baghdad->id]);
        Area::create(['name' => 'Adhamiya', 'parent_id' => $baghdad->id]);
    }
}
