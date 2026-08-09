<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Area;
use App\Models\Agency;
use App\Models\Category;
use App\Models\Report;
use App\Models\WebsiteStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The latest anonymous reports endpoint returns the most recent 6 anonymous reports.
     */
    public function test_latest_anonymous_reports_returns_latest_six_without_auth(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        // Create 6 anonymous reports.
        collect(range(1, 6))->each(function () use ($area, $category) {
            Report::factory()->anonymous()->submitted()->create([
                'area_id' => $area->id,
                'category_id' => $category->id,
                'agency_id' => $category->agency_id,
            ]);
        });

        // Create an older anonymous report that should NOT appear in the latest 6.
        $old = Report::factory()->anonymous()->submitted()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/website/latest-anonymous-reports');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(6, 'data');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($old->id));
    }

    /**
     * The latest anonymous reports endpoint never returns authenticated user reports.
     */
    public function test_latest_anonymous_reports_only_returns_anonymous_reports(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        // A registered (user) report.
        Report::factory()->submitted()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        // An anonymous report.
        $anonymous = Report::factory()->anonymous()->submitted()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->getJson('/api/v1/website/latest-anonymous-reports');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anonymous->id)
            ->assertJsonPath('data.0.reporter.id', null);
    }

    /**
     * The website stats endpoint returns the status table data without authentication.
     */
    public function test_stats_returns_website_status_data_without_auth(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory()->create(['is_active' => true]))->create(['is_active' => true]);

        // 2 anonymous reports, one resolved.
        Report::factory()->anonymous()->resolved()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);
        Report::factory()->anonymous()->submitted()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->getJson('/api/v1/website/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_reports', 2)
            ->assertJsonPath('data.resolved_reports', 1)
            ->assertJsonPath('data.pending_reports', 1)
            ->assertJsonPath('data.anonymous_reports', 2)
            ->assertJsonPath('data.active_categories', 1)
            ->assertJsonPath('data.active_areas', 1)
            ->assertJsonPath('data.active_agencies', 1);
    }

    /**
     * A new anonymous submission refreshes the website stats.
     */
    public function test_new_anonymous_submission_updates_stats(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory()->create(['is_active' => true]))->create(['is_active' => true]);

        $response = $this->postJson('/api/v1/reports/anonymous', [
            'reporter_name' => 'Ali Hassan',
            'category_id' => $category->id,
            'area_id' => $area->id,
            'title' => 'Broken street light',
            'description' => 'The street light has been broken for a week.',
        ]);

        $response->assertCreated();

        $stats = WebsiteStat::firstOrFail();

        $this->assertSame(1, $stats->total_reports);
        $this->assertSame(1, $stats->anonymous_reports);
        $this->assertSame(1, $stats->pending_reports);
        $this->assertSame(0, $stats->resolved_reports);
    }

    /**
     * Resolving a report refreshes the website stats.
     */
    public function test_resolving_a_report_updates_stats(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory()->create(['is_active' => true]))->create(['is_active' => true]);

        $report = Report::factory()->anonymous()->submitted()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        // Simulate the workflow being completed through the status service.
        $report->status = ReportStatus::UnderReview;
        $report->save();
        $report->status = ReportStatus::Accepted;
        $report->save();
        $report->status = ReportStatus::InProgress;
        $report->save();

        app(\App\Services\ReportStatusService::class)->transition(
            $report,
            ReportStatus::Resolved,
            null,
            'Completed.'
        );

        $stats = WebsiteStat::firstOrFail();

        $this->assertSame(1, $stats->total_reports);
        $this->assertSame(1, $stats->resolved_reports);
        $this->assertSame(0, $stats->pending_reports);
    }
}
