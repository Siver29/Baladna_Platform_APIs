<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user can create a post.
     */
    public function test_user_can_create_a_post(): void
    {
        $user = User::factory()->create(['role' => Role::Citizen]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/posts', [
                'title' => 'Waste collection problem',
                'content' => 'The waste has not been collected for days.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Waste collection problem');

        $this->assertDatabaseCount('posts', 1);
    }

    /**
     * A user can update their own post.
     */
    public function test_user_can_update_their_own_post(): void
    {
        $user = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/posts/{$post->id}", [
                'title' => 'Updated title',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    /**
     * A user cannot update another user's post.
     */
    public function test_user_cannot_update_another_users_post(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $other = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/v1/posts/{$post->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    /**
     * A user can delete their own post.
     */
    public function test_user_can_delete_their_own_post(): void
    {
        $user = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * A user cannot delete another user's post.
     */
    public function test_user_cannot_delete_another_users_post(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $other = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertForbidden();
    }

    /**
     * An admin can delete any post.
     */
    public function test_admin_can_delete_any_post(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(204);
    }

    /**
     * A user can add a comment to a post.
     */
    public function test_user_can_add_a_comment(): void
    {
        $user = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'content' => 'This is a comment.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('comments', 1);
    }

    /**
     * A user can update their own comment.
     */
    public function test_user_can_update_their_own_comment(): void
    {
        $user = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $user->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/comments/{$comment->id}", [
                'content' => 'Updated comment.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.content', 'Updated comment.');
    }

    /**
     * A user cannot update another user's comment.
     */
    public function test_user_cannot_update_another_users_comment(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $other = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/v1/comments/{$comment->id}", [
                'content' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    /**
     * A user can delete their own comment.
     */
    public function test_user_can_delete_their_own_comment(): void
    {
        $user = User::factory()->create(['role' => Role::Citizen]);
        $post = Post::factory()->create(['user_id' => $user->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(204);
    }

    /**
     * An admin can delete any comment.
     */
    public function test_admin_can_delete_any_comment(): void
    {
        $owner = User::factory()->create(['role' => Role::Citizen]);
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(204);
    }
}
