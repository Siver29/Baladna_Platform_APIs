<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Area;
use App\Enums\Role;
use App\Enums\AreaStatus;

class UserAreaSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_suggest_an_area()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/user-areas', [
            'name' => 'Test Area',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Test Area',
                    'status' => AreaStatus::PENDING->value,
                ]
            ]);

        $this->assertDatabaseHas('areas', [
            'name' => 'Test Area',
            'status' => AreaStatus::PENDING->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_suggest_an_area()
    {
        $response = $this->postJson('/api/v1/user-areas', [
            'name' => 'Test Area',
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_approve_an_area_suggestion()
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $area = Area::factory()->create(['status' => AreaStatus::PENDING]);

        $this->actingAs($admin);

        $response = $this->patchJson("/api/v1/admin/area-suggestions/{$area->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => AreaStatus::APPROVED->value,
                ]
            ]);

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'status' => AreaStatus::APPROVED->value,
        ]);
    }

    public function test_admin_can_reject_an_area_suggestion()
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $area = Area::factory()->create(['status' => AreaStatus::PENDING]);

        $this->actingAs($admin);

        $response = $this->patchJson("/api/v1/admin/area-suggestions/{$area->id}/reject");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => AreaStatus::REJECTED->value,
                ]
            ]);

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'status' => AreaStatus::REJECTED->value,
        ]);
    }

    public function test_user_can_see_only_approved_areas()
    {
        Area::factory()->create(['status' => AreaStatus::APPROVED]);
        Area::factory()->create(['status' => AreaStatus::PENDING]);
        Area::factory()->create(['status' => AreaStatus::REJECTED]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/user-areas');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_see_pending_areas()
    {
        Area::factory()->create(['status' => AreaStatus::APPROVED]);
        Area::factory()->create(['status' => AreaStatus::PENDING]);
        Area::factory()->create(['status' => AreaStatus::REJECTED]);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/admin/area-suggestions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_see_the_approval_state_of_their_own_suggestions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $pending = Area::factory()->create(['status' => AreaStatus::PENDING, 'user_id' => $user->id]);
        $approved = Area::factory()->create(['status' => AreaStatus::APPROVED, 'user_id' => $user->id]);
        Area::factory()->create(['status' => AreaStatus::PENDING, 'user_id' => $other->id]);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/my-area-suggestions');

        $response->assertOk()->assertJsonCount(2, 'data');

        $statuses = collect($response->json('data'))->pluck('status', 'id');

        $this->assertSame(AreaStatus::PENDING->value, $statuses[$pending->id]);
        $this->assertSame(AreaStatus::APPROVED->value, $statuses[$approved->id]);
    }

    public function test_employee_can_see_whether_a_suggestion_was_approved(): void
    {
        $citizen = User::factory()->create();
        $area = Area::factory()->create(['status' => AreaStatus::APPROVED, 'user_id' => $citizen->id]);

        // Areas created by an admin are not suggestions and stay out of the list.
        Area::factory()->create(['status' => AreaStatus::APPROVED, 'user_id' => null]);

        $employee = User::factory()->employee()->create();
        $this->actingAs($employee);

        $response = $this->getJson('/api/v1/employee/area-suggestions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $area->id)
            ->assertJsonPath('data.0.status', AreaStatus::APPROVED->value)
            ->assertJsonPath('data.0.is_approved', true)
            ->assertJsonPath('data.0.suggested_by.id', $citizen->id);
    }

    public function test_employee_can_filter_suggestions_by_status(): void
    {
        $citizen = User::factory()->create();
        Area::factory()->create(['status' => AreaStatus::PENDING, 'user_id' => $citizen->id]);
        Area::factory()->create(['status' => AreaStatus::REJECTED, 'user_id' => $citizen->id]);

        $employee = User::factory()->employee()->create();
        $this->actingAs($employee);

        $this->getJson('/api/v1/employee/area-suggestions?status=rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', AreaStatus::REJECTED->value);
    }

    public function test_pending_suggestions_are_hidden_from_the_public_area_list(): void
    {
        Area::factory()->create(['status' => AreaStatus::APPROVED]);
        Area::factory()->create(['status' => AreaStatus::PENDING]);

        $this->getJson('/api/v1/areas')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
