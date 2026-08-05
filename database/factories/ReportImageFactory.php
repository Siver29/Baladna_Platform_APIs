<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportImage>
 */
class ReportImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'image_path' => 'reports/' . fake()->uuid() . '.jpg',
        ];
    }
}
