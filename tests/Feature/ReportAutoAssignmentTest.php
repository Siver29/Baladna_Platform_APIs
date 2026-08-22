<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAutoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A report goes straight to the employee responsible for the picked category.
     */
    public function test_report_is_assigned_to_the_category_responsible_employee(): void
    {
        $agency = Agency::factory()->create();
        $responsible = User::factory()->employee($agency->id)->create();
        $category = Category::factory()->create([
            'agency_id' => $agency->id,
            'responsible_employee_id' => $responsible->id,
        ]);

        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Broken street light',
                'description' => 'The light has been off for a week.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.assigned_employee.id', $responsible->id);

        $this->assertDatabaseHas('reports', [
            'title' => 'Broken street light',
            'assigned_employee_id' => $responsible->id,
        ]);
    }

    /**
     * Without a designated employee the least loaded one in the agency takes it.
     */
    public function test_report_falls_back_to_the_least_loaded_agency_employee(): void
    {
        $agency = Agency::factory()->create();
        $busy = User::factory()->employee($agency->id)->create();
        $free = User::factory()->employee($agency->id)->create();

        $category = Category::factory()->create([
            'agency_id' => $agency->id,
            'responsible_employee_id' => null,
        ]);

        Report::factory()->count(3)->create([
            'category_id' => $category->id,
            'agency_id' => $agency->id,
            'assigned_employee_id' => $busy->id,
            'status' => ReportStatus::InProgress,
        ]);

        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Overflowing bin',
                'description' => 'The bin has not been emptied.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.assigned_employee.id', $free->id);
    }

    /**
     * An employee from another agency is never picked as responsible.
     */
    public function test_employee_from_another_agency_is_not_assigned(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $outsider = User::factory()->employee($otherAgency->id)->create();

        $category = Category::factory()->create([
            'agency_id' => $agency->id,
            'responsible_employee_id' => $outsider->id,
        ]);

        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Blocked drain',
                'description' => 'Water is not draining.',
            ]);

        $response->assertCreated();

        $this->assertDatabaseMissing('reports', [
            'title' => 'Blocked drain',
            'assigned_employee_id' => $outsider->id,
        ]);
    }

    /**
     * An anonymous report is assigned the same way.
     */
    public function test_anonymous_report_is_assigned_to_the_responsible_employee(): void
    {
        $agency = Agency::factory()->create();
        $responsible = User::factory()->employee($agency->id)->create();
        $category = Category::factory()->create([
            'agency_id' => $agency->id,
            'responsible_employee_id' => $responsible->id,
        ]);
        $area = Area::factory()->create();

        $response = $this->postJson('/api/v1/reports/anonymous', [
            'reporter_name' => 'Anonymous Citizen',
            'category_id' => $category->id,
            'area_id' => $area->id,
            'title' => 'Damaged pavement',
            'description' => 'The pavement is cracked.',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('reports', [
            'title' => 'Damaged pavement',
            'assigned_employee_id' => $responsible->id,
        ]);
    }

    /**
     * Changing the category hands the report to the new category's owner.
     */
    public function test_changing_the_category_reassigns_the_report(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $employeeA = User::factory()->employee($agencyA->id)->create();
        $employeeB = User::factory()->employee($agencyB->id)->create();

        $categoryA = Category::factory()->create([
            'agency_id' => $agencyA->id,
            'responsible_employee_id' => $employeeA->id,
        ]);
        $categoryB = Category::factory()->create([
            'agency_id' => $agencyB->id,
            'responsible_employee_id' => $employeeB->id,
        ]);

        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();

        $report = Report::factory()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $categoryA->id,
            'agency_id' => $agencyA->id,
            'assigned_employee_id' => $employeeA->id,
            'status' => ReportStatus::Submitted,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->patchJson("/api/v1/reports/{$report->id}", [
                'category_id' => $categoryB->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.assigned_employee.id', $employeeB->id)
            ->assertJsonPath('data.agency.id', $agencyB->id);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'agency_id' => $agencyB->id,
            'assigned_employee_id' => $employeeB->id,
        ]);
    }
}
