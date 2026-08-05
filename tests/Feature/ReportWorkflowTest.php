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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * A citizen can create a report.
     */
    public function test_citizen_can_create_a_report(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::has('agency')->with('agency')->first() ?? Category::factory()->for(Agency::factory())->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Large pothole in the main street',
                'description' => 'The pothole is dangerous.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.agency.id', $category->agency_id);

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_status_histories', 1);
    }

    /**
     * Report creation validates required fields.
     */
    public function test_report_creation_validates_required_fields(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', []);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Public report listing is paginated.
     */
    public function test_public_report_listing_is_paginated(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        Report::factory()->count(15)->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/reports?per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 15)
            ->assertJsonCount(10, 'data');
    }

    /**
     * Reports can be filtered by status.
     */
    public function test_reports_can_be_filtered_by_status(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        Report::factory()->count(2)->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);
        Report::factory()->count(3)->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
            'status' => ReportStatus::Resolved,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/reports?status=submitted');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * A citizen can update their submitted report.
     */
    public function test_citizen_can_update_their_submitted_report(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->patchJson("/api/v1/reports/{$report->id}", [
                'title' => 'Updated title',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    /**
     * A citizen cannot update another user's report.
     */
    public function test_citizen_cannot_update_another_users_report(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $other = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/v1/reports/{$report->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    /**
     * A citizen can cancel their own report.
     */
    public function test_citizen_can_cancel_their_report(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'new_status' => ReportStatus::Cancelled->value,
        ]);
    }

    /**
     * The client cannot set the report status directly on creation.
     */
    public function test_citizen_cannot_set_report_status_directly(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Test',
                'description' => 'Test description',
                'status' => 'resolved',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'submitted');
    }

    /**
     * A citizen can confirm another citizen's report.
     */
    public function test_citizen_can_confirm_another_users_report(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $confirmer = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($confirmer, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.confirmations_count', 1);
    }

    /**
     * A citizen cannot confirm the same report twice.
     */
    public function test_citizen_cannot_confirm_same_report_twice(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $confirmer = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $report->confirmations()->create(['user_id' => $confirmer->id]);

        $response = $this->actingAs($confirmer, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/confirm");

        $response->assertStatus(409);
    }

    /**
     * A citizen cannot confirm their own report.
     */
    public function test_citizen_cannot_confirm_their_own_report(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/confirm");

        $response->assertForbidden();
    }

    /**
     * An employee can view reports from their agency.
     */
    public function test_employee_can_view_reports_from_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        Report::factory()->count(3)->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/employee/reports');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * An employee cannot view reports from another agency.
     */
    public function test_employee_cannot_view_reports_from_another_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $employee = User::factory()->employee($agencyA->id)->create();
        $area = Area::factory()->create();
        $categoryA = Category::factory()->for($agencyA)->create();
        $categoryB = Category::factory()->for($agencyB)->create();

        Report::factory()->count(2)->create([
            'agency_id' => $agencyB->id,
            'category_id' => $categoryB->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/employee/reports');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * An employee can view a single report from their agency.
     */
    public function test_employee_cannot_view_report_from_another_agency_detail(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $employee = User::factory()->employee($agencyA->id)->create();
        $area = Area::factory()->create();
        $categoryB = Category::factory()->for($agencyB)->create();

        $report = Report::factory()->create([
            'agency_id' => $agencyB->id,
            'category_id' => $categoryB->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee/reports/{$report->id}");

        $response->assertForbidden();
    }

    /**
     * An employee can change a valid report status.
     */
    public function test_employee_can_change_a_valid_report_status(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        $report = Report::factory()->submitted()->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/employee/reports/{$report->id}/status", [
                'status' => 'under_review',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'under_review');
    }

    /**
     * An employee cannot make an invalid status transition.
     */
    public function test_employee_cannot_make_invalid_status_transition(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        $report = Report::factory()->submitted()->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/employee/reports/{$report->id}/status", [
                'status' => 'resolved',
                'resolution_note' => 'The team has resolved the issue.',
            ]);

        $response->assertStatus(409);
    }

    /**
     * Resolving a report requires a resolution note.
     */
    public function test_resolving_report_requires_resolution_note(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        $report = Report::factory()->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
            'status' => ReportStatus::InProgress,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/employee/reports/{$report->id}/status", [
                'status' => 'resolved',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_note');
    }

    /**
     * Rejecting a report requires a rejection reason.
     */
    public function test_rejecting_report_requires_rejection_reason(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        $report = Report::factory()->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
            'status' => ReportStatus::UnderReview,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/employee/reports/{$report->id}/status", [
                'status' => 'rejected',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');
    }

    /**
     * Status changes create history records.
     */
    public function test_status_changes_create_history_records(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $area = Area::factory()->create();
        $category = Category::factory()->for($agency)->create();

        $report = Report::factory()->submitted()->create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/employee/reports/{$report->id}/status", [
                'status' => 'under_review',
            ]);

        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'new_status' => ReportStatus::UnderReview->value,
        ]);
    }

    /**
     * The report owner can review a resolved report.
     */
    public function test_report_owner_can_review_resolved_report(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->resolved()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/review", [
                'rating' => 5,
                'comment' => 'Great work!',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);
    }

    /**
     * Another citizen cannot review the report.
     */
    public function test_another_citizen_cannot_review_the_report(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $other = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->resolved()->create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/review", [
                'rating' => 5,
            ]);

        $response->assertForbidden();
    }

    /**
     * Report image upload works.
     */
    public function test_report_image_upload(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $response = $this->actingAs($citizen, 'sanctum')
            ->post("/api/v1/reports/{$report->id}/images", [
                'images' => [$file],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('report_images', 1);
    }

    /**
     * Report image upload rejects non-image files.
     */
    public function test_report_image_upload_rejects_invalid_file(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($citizen, 'sanctum')
            ->post("/api/v1/reports/{$report->id}/images", [
                'images' => [$file],
            ]);

        $response->assertUnprocessable();
    }

    /**
     * The public report resource does not expose email or phone.
     */
    public function test_public_report_resource_does_not_expose_email_or_phone(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen, 'phone' => '+9647000000000']);
        $area = Area::factory()->create();
        $category = Category::factory()->for(Agency::factory())->create();

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'area_id' => $area->id,
            'category_id' => $category->id,
            'agency_id' => $category->agency_id,
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonMissingPath('data.reporter.email')
            ->assertJsonMissingPath('data.reporter.phone')
            ->assertJsonPath('data.reporter.name', $citizen->name);
    }
}
