<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataAndAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Public areas endpoint returns a list.
     */
    public function test_public_areas_endpoint_lists_areas(): void
    {
        Area::factory()->create(['name' => 'Baghdad']);
        Area::factory()->create(['name' => 'Karrada']);

        $response = $this->getJson('/api/v1/areas');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Public categories are filtered by agency.
     */
    public function test_public_categories_can_be_filtered_by_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();

        Category::factory()->create(['agency_id' => $agency->id, 'name' => 'Water leak']);
        Category::factory()->create(['agency_id' => $otherAgency->id, 'name' => 'Damaged road']);

        $response = $this->getJson("/api/v1/categories?agency_id={$agency->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Water leak');
    }

    /**
     * Inactive categories are hidden from the public endpoint.
     */
    public function test_inactive_categories_are_hidden_from_public_endpoint(): void
    {
        $agency = Agency::factory()->create();

        Category::factory()->create(['agency_id' => $agency->id, 'is_active' => false]);
        Category::factory()->create(['agency_id' => $agency->id, 'is_active' => true]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * An admin can create an area.
     */
    public function test_admin_can_create_an_area(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/areas', [
                'name' => 'Zubair',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Zubair');
    }

    /**
     * A non-admin user cannot access admin endpoints.
     */
    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $citizen = User::factory()->create(['role' => Role::Citizen]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/v1/admin/areas');

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You are not authorized to perform this action.');
    }

    /**
     * An unauthenticated user cannot access admin endpoints.
     */
    public function test_admin_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/areas');

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
