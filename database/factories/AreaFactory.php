<?php

namespace Database\Factories;

use App\Enums\AreaStatus;
use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'parent_id' => null,
            'status' => AreaStatus::APPROVED,
            'user_id' => null,
        ];
    }
}
