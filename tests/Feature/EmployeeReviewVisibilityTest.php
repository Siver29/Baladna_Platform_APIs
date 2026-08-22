<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReviewVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a resolved report with a citizen review attached.
     *
     * @return array{0: Report, 1: ReportReview, 2: User}
     */
    private function reviewedReport(Agency $agency, int $rating = 5): array
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $category = Category::factory()->create(['agency_id' => $agency->id]);
        $area = Area::factory()->create();

        $report = Report::factory()->create([
            'user_id' => $citizen->id,
            'category_id' => $category->id,
            'agency_id' => $agency->id,
            'area_id' => $area->id,
            'status' => ReportStatus::Resolved,
        ]);

        $review = ReportReview::factory()->create([
            'report_id' => $report->id,
            'user_id' => $citizen->id,
            'rating' => $rating,
            'comment' => 'The team fixed it quickly.',
        ]);

        return [$report, $review, $citizen];
    }

    public function test_employee_can_list_reviews_for_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();

        [$report, $review, $citizen] = $this->reviewedReport($agency, 4);
        $this->reviewedReport($otherAgency, 2);

        $employee = User::factory()->employee($agency->id)->create();

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/employee/reviews');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.report.id', $report->id)
            ->assertJsonPath('data.0.reviewer.name', $citizen->name)
            ->assertJsonPath('meta.reviews_count', 1)
            ->assertJsonStructure(['meta' => ['average_rating']]);
    }

    public function test_employee_can_see_the_review_of_a_single_report(): void
    {
        $agency = Agency::factory()->create();
        [$report, $review] = $this->reviewedReport($agency, 3);

        $employee = User::factory()->employee($agency->id)->create();

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee/reports/{$report->id}/review");

        $response->assertOk()
            ->assertJsonPath('data.id', $review->id)
            ->assertJsonPath('data.rating', 3)
            ->assertJsonPath('data.comment', 'The team fixed it quickly.');
    }

    public function test_review_endpoint_returns_404_when_the_report_has_no_review(): void
    {
        $agency = Agency::factory()->create();
        $category = Category::factory()->create(['agency_id' => $agency->id]);

        $report = Report::factory()->create([
            'category_id' => $category->id,
            'agency_id' => $agency->id,
            'status' => ReportStatus::Resolved,
        ]);

        $employee = User::factory()->employee($agency->id)->create();

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee/reports/{$report->id}/review")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_employee_cannot_see_a_review_from_another_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        [$report] = $this->reviewedReport($otherAgency);

        $employee = User::factory()->employee($agency->id)->create();

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee/reports/{$report->id}/review")
            ->assertForbidden();
    }

    public function test_report_detail_exposes_the_review_to_the_employee(): void
    {
        $agency = Agency::factory()->create();
        [$report, $review] = $this->reviewedReport($agency, 5);

        $employee = User::factory()->employee($agency->id)->create();

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id)
            ->assertJsonPath('data.review.rating', 5);
    }

    public function test_citizen_cannot_use_the_employee_reviews_endpoint(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);

        $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/employee/reviews')
            ->assertForbidden();
    }
}
