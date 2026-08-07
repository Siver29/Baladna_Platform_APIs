<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnonymousReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An anonymous user can submit a report without authentication.
     */
    public function test_anonymous_user_can_submit_a_report_without_auth(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $response = $this->postJson('/api/v1/reports/anonymous', [
            'reporter_name' => 'Ali Hassan',
            'reporter_email' => 'ali@example.com',
            'reporter_phone' => '+9647000000000',
            'category_id' => $category->id,
            'area_id' => $area->id,
            'title' => 'Broken street light',
            'description' => 'The street light has been broken for a week.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ReportStatus::Submitted->value)
            ->assertJsonPath('data.agency.id', $category->agency_id)
            ->assertJsonPath('data.reporter.id', null)
            ->assertJsonPath('data.reporter.name', 'Ali Hassan');

        $this->assertDatabaseHas('reports', [
            'user_id' => null,
            'reporter_name' => 'Ali Hassan',
            'reporter_email' => 'ali@example.com',
            'reporter_phone' => '+9647000000000',
        ]);

        // The initial status history record should have a null actor.
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => Report::first()->id,
            'user_id' => null,
            'new_status' => ReportStatus::Submitted->value,
        ]);
    }

    /**
     * Anonymous report creation validates required reporter fields.
     */
    public function test_anonymous_report_requires_reporter_name(): void
    {
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $response = $this->postJson('/api/v1/reports/anonymous', [
            'category_id' => $category->id,
            'area_id' => $area->id,
            'title' => 'Broken street light',
            'description' => 'The street light has been broken for a week.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reporter_name');

        $this->assertDatabaseCount('reports', 0);
    }

    /**
     * Anonymous report creation validates standard report fields.
     */
    public function test_anonymous_report_validates_standard_report_fields(): void
    {
        $response = $this->postJson('/api/v1/reports/anonymous', [
            'reporter_name' => 'Ali Hassan',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'area_id', 'title', 'description']);

        $this->assertDatabaseCount('reports', 0);
    }

    /**
     * An employee can view an anonymous report from their agency.
     */
    public function test_employee_can_view_anonymous_report_from_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $employee = \App\Models\User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        $report = Report::factory()->anonymous()->submitted()->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.reporter.name', $report->reporter_name)
            ->assertJsonPath('data.reporter.id', null);
    }

    /**
     * An anonymous report cannot be updated/cancelled by a random citizen.
     */
    public function test_anonymous_report_cannot_be_managed_by_random_citizen(): void
    {
        $citizen = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->anonymous()->submitted()->create([
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->patchJson("/api/v1/reports/{$report->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();

        $cancelResponse = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/cancel");

        $cancelResponse->assertForbidden();
    }
}

