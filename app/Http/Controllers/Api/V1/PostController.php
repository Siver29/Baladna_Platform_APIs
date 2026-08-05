<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StorePostRequest;
use App\Http\Requests\Community\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use ApiResponse;

    /**
     * List community posts.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $posts = Post::query()
            ->with(['user', 'area'])
            ->withCount('comments')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successCollection(
            PostResource::collection($posts),
            200,
            [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'last_page' => $posts->lastPage(),
            ]
        );
    }

    /**
     * Create a new post.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create([
            'user_id' => $request->user()->id,
            'area_id' => $request->area_id,
            'title' => $request->title,
            'content' => $request->content,
        ]);

        $post->load('user', 'area');

        return $this->success(new PostResource($post), 'Post created successfully.', 201);
    }

    /**
     * Show a single post with its comments.
     */
    public function show(Post $post): JsonResponse
    {
        $post->load(['user', 'area', 'comments.user']);

        return $this->success(new PostResource($post));
    }

    /**
     * Update the authenticated user's own post.
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post->update($request->validated());

        $post->load('user', 'area');

        return $this->success(new PostResource($post), 'Post updated successfully.');
    }

    /**
     * Delete the user's own post (or any post for admins).
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return $this->success(null, 'Post deleted successfully.', 204);
    }
}
