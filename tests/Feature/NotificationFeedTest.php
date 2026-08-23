<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\User;
use App\Services\ReportStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The reporter is notified when an employee moves their report along.
     */
    public function test_citizen_sees_status_changes_on_their_own_report(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $citizen = User::factory()->create(['role' => Role::Citizen]);

        $report = Report::factory()->submitted()->create([
            'user_id' => $citizen->id,
            'agency_id' => $agency->id,
            'assigned_employee_id' => $employee->id,
        ]);

        app(ReportStatusService::class)->transition(
            $report,
            ReportStatus::UnderReview,
            $employee,
            'Taking a look now.'
        );

        $response = $this->actingAs($citizen, 'sanctum')->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'report_status_changed')
            ->assertJsonPath('data.0.old_status', 'submitted')
            ->assertJsonPath('data.0.new_status', 'under_review')
            ->assertJsonPath('data.0.note', 'Taking a look now.')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('data.0.report.id', $report->id)
            ->assertJsonPath('data.0.report.reference_number', $report->reference_number)
            ->assertJsonPath('data.0.actor.id', $employee->id)
            ->assertJsonPath('meta.unread_count', 1);
    }

    /**
     * An event the user caused themselves is not news to them.
     */
    public function test_own_actions_are_excluded_from_the_feed(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $report = Report::factory()->submitted()->create(['user_id' => $citizen->id]);

        app(ReportStatusService::class)->transition(
            $report,
            ReportStatus::Cancelled,
            $citizen
        );

        $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
    }

    /**
     * The assignment history row reaches the employee it assigned the report to.
     */
    public function test_employee_sees_the_auto_assignment_event(): void
    {
        $agency = Agency::factory()->create();
        $employee = User::factory()->employee($agency->id)->create();
        $category = Category::factory()->create([
            'agency_id' => $agency->id,
            'responsible_employee_id' => $employee->id,
        ]);
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $area = Area::factory()->create();

        $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Overflowing bin',
                'description' => 'It has not been emptied in two weeks.',
            ])
            ->assertCreated();

        // Two events reach the employee: the report was filed, and it landed
        // on them. The assignment is recorded last, so it sorts first.
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'report_assigned')
            ->assertJsonPath('data.0.report.title', 'Overflowing bin')
            ->assertJsonPath('data.1.type', 'report_created')
            ->assertJsonPath('meta.unread_count', 2);
    }

    /**
     * A user never sees events belonging to somebody else's report.
     */
    public function test_feed_is_scoped_to_the_authenticated_user(): void
    {
        $employee = User::factory()->employee()->create();
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $stranger = User::factory()->create(['role' => Role::Citizen]);

        $report = Report::factory()->submitted()->create(['user_id' => $owner->id]);

        app(ReportStatusService::class)->transition($report, ReportStatus::UnderReview, $employee);

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
    }

    /**
     * Marking as read moves the watermark and empties the unread filter.
     */
    public function test_marking_as_read_clears_the_unread_count(): void
    {
        $employee = User::factory()->employee()->create();
        $citizen = User::factory()->create(['role' => Role::Citizen]);
        $report = Report::factory()->submitted()->create(['user_id' => $citizen->id]);

        app(ReportStatusService::class)->transition($report, ReportStatus::UnderReview, $employee);

        $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/notifications/read')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull($citizen->fresh()->notifications_read_at);

        // The event is still in the feed, now flagged as read...
        $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_read', true)
            ->assertJsonPath('meta.unread_count', 0);

        // ...but the unread filter no longer returns it.
        $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/notifications?unread_only=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * An event recorded after the watermark counts as unread again.
     */
    public function test_events_after_the_watermark_are_unread(): void
    {
        $employee = User::factory()->employee()->create();
        $citizen = User::factory()->create([
            'role' => Role::Citizen,
            'notifications_read_at' => now()->subDay(),
        ]);
        $report = Report::factory()->submitted()->create(['user_id' => $citizen->id]);

        ReportStatusHistory::create([
            'report_id' => $report->id,
            'user_id' => $employee->id,
            'old_status' => 'submitted',
            'new_status' => 'under_review',
            'note' => 'Old news.',
        ])->forceFill(['created_at' => now()->subDays(2)])->save();

        app(ReportStatusService::class)->transition($report, ReportStatus::UnderReview, $employee);

        $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('data.1.is_read', true)
            ->assertJsonPath('meta.unread_count', 1);
    }

    /**
     * The feed is private.
     */
    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read')->assertUnauthorized();
    }
}
