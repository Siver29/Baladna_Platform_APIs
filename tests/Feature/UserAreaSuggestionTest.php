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
        $admin = User::factory()->create(['role' => Role::ADMIN]);
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
        $admin = User::factory()->create(['role' => Role::ADMIN]);
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

        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/admin/area-suggestions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
