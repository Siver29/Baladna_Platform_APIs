<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = Category::inRandomOrder()->first() ?? Category::factory();

        return [
            'reference_number' => 'BLD-' . fake()->year() . '-' . str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'category_id' => $category,
            'area_id' => Area::factory(),
            'agency_id' => $category instanceof Category ? $category->agency_id : Agency::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => fake()->randomElement(ReportStatus::cases()),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::Submitted]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Resolved,
            'resolution_note' => fake()->sentence(),
            'resolved_at' => now(),
        ]);
    }
}
