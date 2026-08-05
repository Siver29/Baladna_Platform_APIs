<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportConfirmation;
use App\Models\ReportStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Seed demo reports, confirmations, history, and a review.
     */
    public function run(): void
    {
        $citizen = User::where('email', 'citizen@baladna.test')->first() ?? User::factory()->create(['role' => Role::Citizen]);
        $employee = User::where('email', 'employee@baladna.test')->first() ?? User::factory()->employee()->create();

        $areas = Area::pluck('id')->all();
        $categories = Category::with('agency')->get();

        $confirmers = User::factory()->count(4)->create(['role' => Role::Citizen]);

        foreach (range(1, 15) as $i) {
            $category = $categories->random();

            $report = Report::create([
                'reference_number' => 'BLD-' . now()->year . '-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'user_id' => $citizen->id,
                'category_id' => $category->id,
                'area_id' => $areas[array_rand($areas)],
                'agency_id' => $category->agency_id,
                'title' => "Demo report {$i}: " . $category->name,
                'description' => "This is a demo civic issue report number {$i}.",
                'address' => 'Karrada, Baghdad',
                'latitude' => 33.30 + ($i * 0.001),
                'longitude' => 44.36 + ($i * 0.001),
                'priority' => Priority::Normal,
                'status' => ReportStatus::Submitted,
            ]);

            ReportStatusHistory::create([
                'report_id' => $report->id,
                'user_id' => $citizen->id,
                'old_status' => null,
                'new_status' => ReportStatus::Submitted->value,
                'note' => 'Report submitted.',
            ]);

            foreach ($confirmers->take(rand(0, 3)) as $confirmer) {
                ReportConfirmation::create([
                    'report_id' => $report->id,
                    'user_id' => $confirmer->id,
                ]);
            }
        }

        // One resolved report with a review.
        $resolved = Report::create([
            'reference_number' => 'BLD-' . now()->year . '-' . str_pad('100', 6, '0', STR_PAD_LEFT),
            'user_id' => $citizen->id,
            'category_id' => $categories->first()->id,
            'area_id' => $areas[0] ?? null,
            'agency_id' => $categories->first()->agency_id,
            'title' => 'Resolved water leak on Al-Saadoon Street',
            'description' => 'A water leak was fixed after it was reported.',
            'address' => 'Al-Saadoon Street, Baghdad',
            'priority' => Priority::High,
            'status' => ReportStatus::Resolved,
            'resolution_note' => 'The leaking pipe was repaired.',
            'resolved_at' => now()->subDay(),
        ]);

        ReportStatusHistory::create([
            'report_id' => $resolved->id,
            'user_id' => $citizen->id,
            'old_status' => null,
            'new_status' => ReportStatus::Submitted->value,
            'note' => 'Report submitted.',
        ]);

        ReportStatusHistory::create([
            'report_id' => $resolved->id,
            'user_id' => $employee->id,
            'old_status' => ReportStatus::Submitted->value,
            'new_status' => ReportStatus::Resolved->value,
            'note' => 'The leaking pipe was repaired.',
        ]);

        $resolved->review()->create([
            'user_id' => $citizen->id,
            'rating' => 5,
            'comment' => 'The issue was fixed quickly. Thank you!',
        ]);
    }
}
